<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Models\RecipeCostSnapshot;

/**
 * Freezes a recipe's currently-computed cost into a permanent record, tied
 * to the production run that just made it real. CalculateRecipeCost
 * recomputes live from current ingredient prices on every page load, so
 * without this, an old cost figure is simply unrecoverable once a price
 * changes -- this Action is what makes historical cost tracking possible.
 */
class CreateRecipeCostSnapshot
{
    public function __construct(
        private readonly CalculateRecipeCost $calculateRecipeCost,
    ) {}

    public function handle(Recipe $recipe, ProductionRun $productionRun, int $jarsProduced): RecipeCostSnapshot
    {
        $cost = $this->calculateRecipeCost->handle($recipe);

        return RecipeCostSnapshot::create([
            'recipe_id' => $recipe->id,
            'production_run_id' => $productionRun->id,
            'jars_produced' => $jarsProduced,
            'sell_price' => $recipe->sell_price,
            'fill_size_g' => $recipe->fill_size_g,
            'cost_buffer_percent' => $recipe->cost_buffer_percent,
            'raw_cost' => $cost['raw_cost'],
            'yield_grams' => $cost['yield_grams'],
            'buffered_cost' => $cost['buffered_cost'],
            'actual_cost_per_jar' => $cost['actual_cost_per_jar'],
            'food_cost_percent' => $cost['food_cost_percent'],
            'any_stale' => $cost['any_stale'],
            'any_missing' => $cost['any_missing'],
            'ingredient_breakdown' => $cost['ingredient_breakdown'],
        ]);
    }
}
