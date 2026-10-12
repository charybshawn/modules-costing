<?php

namespace Cultpantry\Costing\Actions;

use App\Models\User;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\ProductionRun;
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
 * Each size allocated on the run (ProductionRunAllocation) gets its own cost
 * snapshot and, when the size is linked to a finished good
 * (RecipeSize::finishedGood()), has its actual_units credited to it via the
 * host app's own stock-mutation funnel (see
 * Cultpantry\Costing\Contracts\FinishedGood) -- this package never sees
 * stock_quantity or how the host locks/audits/broadcasts the write. Sizes
 * with no linked finished good are unaffected.
 */
class CompleteProductionRun
{
    public function __construct(
        private readonly CheckRunAllocation $checkRunAllocation,
        private readonly CalculateProductionPlan $calculateProductionPlan,
        private readonly CreateRecipeCostSnapshot $createRecipeCostSnapshot,
        private readonly RecordInventoryAdjustment $recordInventoryAdjustment,
        private readonly BuildRecipeSheet $buildRecipeSheet = new BuildRecipeSheet,
    ) {}

    /**
     * @param array<int, int> $actuals recipe_size_id => actual units produced,
     *     for sizes where it differs from the allocation. Never affects
     *     ingredient deduction below (that's always planned quantities) --
     *     purely overrides what gets frozen into that size's cost
     *     snapshot and, from there, ProductionRun::totalUnits() -- and also
     *     what gets credited to a linked product's stock.
     * @param User|null $actor the user completing the run, passed straight
     *     through to the host's own audit trail for any linked finished
     *     good. Null is fine (system/unattended completion).
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

        // The run's purchase order and recipe sheets as they stand right
        // now, before its stock is deducted -- kept as the run's record.
        $records = [
            'purchase_order_record' => $plan,
            'recipe_sheet_record' => $this->buildRecipeSheet->forRun($productionRun),
        ];

        foreach ($plan['rows'] as $row) {
            $required = (float) $row['required'];
            if ($required <= 0) {
                continue;
            }

            $ingredient = Ingredient::with('packageSizes')->find($row['ingredient_id']);
            if (!$ingredient) {
                continue;
            }

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

            if ($remaining > 0) {
                $shortfalls[] = "{$ingredient->name} (short by ".rtrim(rtrim(number_format($remaining, 2), '0'), '.')." {$ingredient->unit_type})";
            }
        }

        // Fills must fit what the batches make -- checked here as well as
        // on save, since batches can be edited after the allocation was.
        $problem = $this->checkRunAllocation->firstProblem($productionRun);
        abort_if($problem !== null, 422, $problem);

        foreach ($productionRun->allocations()->with('recipeSize.recipe')->get() as $allocation) {
            $size = $allocation->recipeSize;
            $actualUnits = $actuals[$size->id] ?? $allocation->units;

            // Recorded even when it equals the plan -- makes "was this ever
            // overridden" unambiguous later, and is what
            // ProductionRun::totalUnits() reads once this run is completed.
            $allocation->update(['actual_units' => $actualUnits]);

            $this->createRecipeCostSnapshot->handle($size->recipe, $productionRun, $size, $actualUnits);

            // Only sizes linked to a finished good credit anything.
            $finishedGood = $size->finishedGood();
            if ($finishedGood && $actualUnits > 0) {
                $finishedGood->credit(
                    units: $actualUnits,
                    reason: 'production_run',
                    actor: $actor,
                    metadata: [
                        'production_run_id' => $productionRun->id,
                        'recipe_id' => $size->recipe_id,
                        'recipe_size_id' => $size->id,
                        'units' => $actualUnits,
                    ],
                );
            }
        }

        $productionRun->update(['completed_at' => now()] + $records);

        return $shortfalls;
    }
}
