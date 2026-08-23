<?php

namespace Cultpantry\Costing\Actions;

use App\Actions\SyncInventory;
use App\Models\User;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Support\Facades\DB;

/**
 * Marks a production run complete and draws its required ingredient
 * quantities down from Inventory. Reuses CalculateProductionPlan for the
 * exact required-per-ingredient numbers rather than recomputing anything.
 * Also freezes a cost snapshot per recipe in the run -- this is the one
 * and only trigger point for historical cost tracking (event-driven off
 * production, no manual snapshot path), so it belongs here rather than in
 * the controller.
 *
 * For any recipe linked to a storefront Product (Recipe::product), also
 * credits that recipe's actual_units to the product's stock_quantity via
 * SyncInventory -- the app's single stock-mutation funnel, so the write is
 * locked, audited (an inventory.adjusted Event), and broadcast the same as
 * every other stock change. Recipes with no linked product are unaffected.
 */
class CompleteProductionRun
{
    public function __construct(
        private readonly CalculateProductionPlan $calculateProductionPlan,
        private readonly CheckIngredientLowStock $checkIngredientLowStock,
        private readonly CreateRecipeCostSnapshot $createRecipeCostSnapshot,
        private readonly RecordInventoryAdjustment $recordInventoryAdjustment,
    ) {}

    /**
     * @param array<int, int> $actuals recipe_id => actual units produced,
     *     for recipes where it differs from the plan. Never affects
     *     ingredient deduction below (that's always planned quantities) --
     *     purely overrides what gets frozen into that recipe's cost
     *     snapshot and, from there, ProductionRun::totalUnits() -- and now
     *     also what gets credited to a linked product's stock.
     * @param User|null $actor the user completing the run, threaded through
     *     to the inventory.adjusted audit row for any linked product. Null
     *     is fine (system/unattended completion) -- SyncInventory accepts it.
     * @return array<int, string> human-readable shortfall warnings, one per
     *     ingredient that ran out of stock before its required quantity was
     *     fully drawn down -- empty when every requirement was covered.
     */
    public function handle(ProductionRun $productionRun, array $actuals = [], ?User $actor = null): array
    {
        return DB::transaction(fn () => $this->run($productionRun, $actuals, $actor));
    }

    /**
     * @param array<int, int> $actuals
     * @return array<int, string>
     */
    private function run(ProductionRun $productionRun, array $actuals, ?User $actor): array
    {
        // Re-fetched and locked, not the instance the controller passed in --
        // closes a race where two near-simultaneous completion requests (a
        // double-click before the button disables, a retried request, two
        // open tabs) both read completed_at as null before either commits
        // and both deduct inventory. The controller's own abort_if is only a
        // cheap early rejection for the common non-race case; this is the
        // actual guarantee, since it's inside the transaction and blocks a
        // second concurrent caller until the first one commits.
        $productionRun = ProductionRun::whereKey($productionRun->id)->lockForUpdate()->firstOrFail();
        abort_if($productionRun->completed_at, 422, 'This run has already been completed.');

        $shortfalls = [];

        $plan = $this->calculateProductionPlan->handle($productionRun);

        foreach ($plan['rows'] as $row) {
            $required = (float) $row['required'];
            if ($required <= 0) {
                continue;
            }

            $ingredient = Ingredient::with('packageSizes')->find($row['ingredient_id']);
            if (!$ingredient) {
                continue;
            }

            $oldOnHand = (float) $ingredient->packageSizes->sum('quantity_on_hand');

            // Preferred source drained first (same strict provider+brand
            // pair match CalculateIngredientCosting uses for pricing --
            // see GetIngredientPriceOptions::isPreferred() for why this is
            // an exact pair match, not "any brand from this provider"),
            // spilling into the remaining sources in their existing order
            // only once it's empty.
            $isPreferred = fn (PackageSize $packageSize) => $ingredient->preferred_source
                && $packageSize->provider === $ingredient->preferred_source
                && $packageSize->brand === $ingredient->preferred_brand;

            $sources = $ingredient->packageSizes->sortByDesc($isPreferred)->values();

            $remaining = $required;

            foreach ($sources as $packageSize) {
                if ($remaining <= 0) {
                    break;
                }

                // Re-fetched under lock, not the eager-loaded value above --
                // guards against a lost update if a manual inventory
                // adjustment on this same source (InventoryController::
                // adjustSource/bulkUpdate) commits between this loop
                // starting and this row being written.
                $packageSize = PackageSize::whereKey($packageSize->id)->lockForUpdate()->first();
                if (!$packageSize) {
                    continue;
                }

                $available = (float) $packageSize->quantity_on_hand;
                if ($available <= 0) {
                    continue;
                }

                $consumed = min($available, $remaining);
                $after = $available - $consumed;

                $packageSize->update(['quantity_on_hand' => $after]);

                $this->recordInventoryAdjustment->handle(
                    packageSize: $packageSize,
                    reason: 'production_run',
                    onHandBefore: $available,
                    onHandAfter: $after,
                    productionRun: $productionRun,
                );

                $remaining -= $consumed;
            }

            // Floors at 0 automatically -- $remaining only stays > 0 here if
            // every source ran out before covering $required, same "don't
            // go negative" floor the old single-column decrement had.
            $newOnHand = $oldOnHand - ($required - $remaining);

            if ($remaining > 0) {
                $shortfalls[] = "{$ingredient->name} (short by ".rtrim(rtrim(number_format($remaining, 2), '0'), '.')." {$ingredient->unit_type})";
            }

            $this->checkIngredientLowStock->handle($ingredient, $oldOnHand, $newOnHand);
        }

        // $productionRun->recipes was already loaded (with the batches
        // pivot) by calculateProductionPlan->handle() above. 'recipes.product'
        // wasn't part of that eager load (CalculateProductionPlan has no
        // reason to know about it), so top it up here -- $recipe->product is
        // read below, and this app's Model::preventLazyLoading() (outside
        // production) throws on an un-eager-loaded access.
        $productionRun->loadMissing('recipes.product');

        /** @var Recipe $recipe */
        foreach ($productionRun->recipes as $recipe) {
            $plannedUnits = $productionRun->batch_size * (int) $recipe->pivot->batches;
            if ($plannedUnits <= 0) {
                continue;
            }

            $actualUnits = $actuals[$recipe->id] ?? $plannedUnits;

            // Recorded on the pivot even when it equals the plan -- makes
            // "was this ever overridden" unambiguous later, and is what
            // ProductionRun::totalUnits() reads once this run is completed.
            $productionRun->recipes()->updateExistingPivot($recipe->id, ['actual_units' => $actualUnits]);

            $this->createRecipeCostSnapshot->handle($recipe, $productionRun, $actualUnits);

            // Only recipes linked to a storefront product credit finished
            // goods -- most recipes have no product_id yet (nullable, by
            // design), and must behave exactly as before this feature.
            if ($recipe->product_id) {
                app(SyncInventory::class)->applyChange(
                    product: $recipe->product,
                    newQuantity: $recipe->product->stock_quantity + $actualUnits,
                    reason: SyncInventory::REASONS['PRODUCTION_RUN'],
                    actor: $actor,
                    metadata: [
                        'production_run_id' => $productionRun->id,
                        'recipe_id' => $recipe->id,
                        'units' => $actualUnits,
                    ],
                );
            }
        }

        $productionRun->update(['completed_at' => now()]);

        return $shortfalls;
    }
}
