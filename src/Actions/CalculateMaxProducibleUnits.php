<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Recipe;

/**
 * The classic limiting-ingredient calculation: how many units (jars) of this
 * recipe could be produced right now given current on-hand ingredient stock,
 * ignoring any chosen batch_size/target (that's CalculateProductionPlan's
 * job, for an already-created ProductionRun). For each main ingredient,
 * on_hand / quantity_per_jar gives how many units THAT ingredient alone
 * could support -- the recipe as a whole is bottlenecked by whichever
 * ingredient runs out first, so the answer is the minimum across all of
 * them. Byproducts are excluded (Recipe::mainIngredients already scopes
 * that), matching every other costing/planning calculation in this module.
 *
 * Used today purely to drive a visual "needs reordering" indicator
 * (Recipe::min_stock_threshold), but deliberately kept as its own Action --
 * not inlined in a controller -- so a future alert feature (mirroring how
 * CalculateIngredientCosting/CalculateRecipeCost are already reused across
 * several call sites) can call this same calculation without redoing it.
 */
class CalculateMaxProducibleUnits
{
    public function handle(Recipe $recipe): int
    {
        $recipe->loadMissing('mainIngredients.inventory');

        $producible = null;

        foreach ($recipe->mainIngredients as $ingredient) {
            $qty = (float) $ingredient->pivot->quantity_per_jar;
            if ($qty <= 0) {
                continue;
            }

            $possible = (int) floor(((float) $ingredient->inventory->on_hand) / $qty);
            $producible = $producible === null ? $possible : min($producible, $possible);
        }

        // No main ingredients (or all zero-quantity) means there's nothing
        // to bottleneck on, but also nothing to actually produce from --
        // treat that as zero rather than an unbounded/unknown value.
        return $producible ?? 0;
    }
}
