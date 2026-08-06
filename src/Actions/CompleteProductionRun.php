<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
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
    ) {}

    public function handle(ProductionRun $productionRun): void
    {
        $plan = $this->calculateProductionPlan->handle($productionRun);

        foreach ($plan['rows'] as $row) {
            if ((float) $row['required'] <= 0) {
                continue;
            }

            $ingredient = Ingredient::with('inventory')->find($row['ingredient_id']);
            $inventory = $ingredient?->inventory;

            if (!$inventory) {
                continue;
            }

            $oldOnHand = (float) $inventory->on_hand;

            if ($inventory->counted_on_hand !== null) {
                // Walk-in-counted stock (Inventory/Edit.vue's per-brand
                // table) -- the counted total is the raw ground truth, so
                // subtract required grams/units directly, no unit_size
                // division involved.
                $inventory->update(['counted_on_hand' => max(0, (float) $inventory->counted_on_hand - (float) $row['required'])]);
            } elseif ((float) $inventory->unit_size > 0) {
                $newUnitsOnHand = max(0, (float) $inventory->units_on_hand - ((float) $row['required'] / (float) $inventory->unit_size));
                $inventory->update(['units_on_hand' => $newUnitsOnHand]);
            } else {
                // No meaningful inventory tracking set up for this
                // ingredient (unit_size 0, same "not configured yet" state
                // Ingredient::booted() creates by default, and no counted
                // total either) -- nothing to decrement from.
                continue;
            }

            $this->checkIngredientLowStock->handle($ingredient, $oldOnHand, (float) $inventory->fresh()->on_hand);
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
