<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;

/**
 * Marks a production run complete and draws its required ingredient
 * quantities down from Inventory. Reuses CalculateProductionPlan for the
 * exact required-per-ingredient numbers rather than recomputing anything.
 * Also freezes a cost snapshot per recipe in the run -- this is the one
 * and only trigger point for historical cost tracking (event-driven off
 * production, no manual snapshot path), so it belongs here rather than in
 * the controller.
 */
class CompleteProductionRun
{
    public function __construct(
        private readonly CalculateProductionPlan $calculateProductionPlan,
        private readonly CheckIngredientLowStock $checkIngredientLowStock,
        private readonly CreateRecipeCostSnapshot $createRecipeCostSnapshot,
        private readonly RecordInventoryAdjustment $recordInventoryAdjustment,
    ) {}

    public function handle(ProductionRun $productionRun): void
    {
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

            // Preferred source drained first (same provider/brand match
            // CalculateIngredientCosting already uses for pricing --
            // preferred_brand only narrows the match when it's actually
            // set, otherwise any brand from the preferred provider
            // qualifies), spilling into the remaining sources in their
            // existing order only once it's empty.
            $isPreferred = fn (PackageSize $packageSize) => $ingredient->preferred_source
                && $packageSize->provider === $ingredient->preferred_source
                && (!$ingredient->preferred_brand || $packageSize->brand === $ingredient->preferred_brand);

            $sources = $ingredient->packageSizes->sortByDesc($isPreferred)->values();

            $remaining = $required;

            foreach ($sources as $packageSize) {
                if ($remaining <= 0) {
                    break;
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

            $this->checkIngredientLowStock->handle($ingredient, $oldOnHand, $newOnHand);
        }

        // $productionRun->recipes was already loaded (with the batches
        // pivot) by calculateProductionPlan->handle() above.
        /** @var Recipe $recipe */
        foreach ($productionRun->recipes as $recipe) {
            $unitsProduced = $productionRun->batch_size * (int) $recipe->pivot->batches;
            if ($unitsProduced <= 0) {
                continue;
            }

            $this->createRecipeCostSnapshot->handle($recipe, $productionRun, $unitsProduced);
        }

        $productionRun->update(['completed_at' => now()]);
    }
}
