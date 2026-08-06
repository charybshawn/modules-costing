<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\Recipe;

/**
 * Ports the "Food Cost %" section of the original spreadsheet (per-flavour
 * Cost / Cost+buffer / Cost-per-KG / Food Cost %), reusing
 * CalculateIngredientCosting per ingredient the same way CalculateProductionPlan
 * does. Two deliberate corrections from the sheet as originally built,
 * both discussed with and approved by the user:
 *
 * 1. The cost buffer is a configurable per-recipe % (Recipe::cost_buffer_percent),
 *    not a hardcoded 10% -- it's a business policy call, not a constant.
 * 2. The "yield" weight sum only includes gram-based ingredients. The
 *    sheet added packaging item counts (e.g. a deli cup's "1") straight
 *    into a kilogram total, which isn't physically meaningful -- a
 *    container isn't part of the product's mass. Packaging cost still
 *    counts toward raw cost, just not toward the weight/yield figure.
 */
class CalculateRecipeCost
{
    public function __construct(
        private readonly CalculateIngredientCosting $calculateIngredientCosting,
    ) {}

    /**
     * @return array{
     *     raw_cost: float,
     *     yield_grams: float,
     *     buffered_cost: float,
     *     actual_cost_per_jar: float,
     *     food_cost_percent: float|null,
     *     any_stale: bool,
     *     any_missing: bool,
     *     ingredient_breakdown: array<int, array{
     *         ingredient_id: int,
     *         name: string,
     *         unit_type: string,
     *         quantity_per_jar: float,
     *         effective_price: float|null,
     *         cost_contribution: float,
     *         status: 'ok'|'stale'|'missing',
     *     }>,
     * }
     */
    public function handle(Recipe $recipe): array
    {
        // 'priceHistory.ingredient' required: PriceHistoryEntry::price_per_unit
        // reads $this->ingredient internally, and without this eager load
        // that lazy-loads per entry -- throws under this app's
        // Model::preventLazyLoading() outside production. 'inventory' and
        // 'packageSizes' avoid an N+1 inside CalculateIngredientCosting
        // (unused by this Action's own output, but still read per ingredient).
        $recipe->loadMissing('mainIngredients.priceHistory.ingredient', 'mainIngredients.inventory', 'mainIngredients.packageSizes');

        $rawCost = 0.0;
        $yieldGrams = 0.0;
        $anyStale = false;
        $anyMissing = false;
        $ingredientBreakdown = [];

        foreach ($recipe->mainIngredients as $ingredient) {
            $qty = (float) $ingredient->pivot->quantity_per_jar;
            if ($qty <= 0) {
                continue;
            }

            // Weight/yield is a physical fact about the recipe, independent
            // of whether we currently have price data for the ingredient --
            // counted unconditionally so a missing price never silently
            // shrinks the yield denominator (which would inflate the
            // computed cost-per-jar for a given fill size).
            if ($ingredient->isGramBased()) {
                $yieldGrams += $qty;
            }

            $costing = $this->calculateIngredientCosting->handle($ingredient);
            $effectivePrice = $costing['status'] === 'ok' ? $costing['effective_price'] : $costing['stale_effective_price'];

            if ($effectivePrice === null) {
                $anyMissing = true;
                $ingredientBreakdown[] = [
                    'ingredient_id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'unit_type' => $ingredient->unit_type,
                    'quantity_per_jar' => $qty,
                    'effective_price' => null,
                    'cost_contribution' => 0.0,
                    'status' => 'missing',
                ];
                continue;
            }

            if ($costing['status'] !== 'ok') {
                $anyStale = true;
            }

            $costContribution = $ingredient->isGramBased()
                ? ($qty * $effectivePrice / 1000)
                : ($qty * $effectivePrice);

            $rawCost += $costContribution;

            $ingredientBreakdown[] = [
                'ingredient_id' => $ingredient->id,
                'name' => $ingredient->name,
                'unit_type' => $ingredient->unit_type,
                'quantity_per_jar' => $qty,
                'effective_price' => $effectivePrice,
                'cost_contribution' => round($costContribution, 4),
                'status' => $costing['status'] === 'ok' ? 'ok' : 'stale',
            ];
        }

        $bufferPercent = $recipe->cost_buffer_percent !== null ? (float) $recipe->cost_buffer_percent : 0.0;
        $bufferedCost = $rawCost * (1 + $bufferPercent / 100);

        $fillSizeG = $recipe->fill_size_g !== null ? (float) $recipe->fill_size_g : null;

        // No measured fill size yet -- the best available estimate is the
        // buffered total-batch cost per jar (i.e. assume the theoretical
        // yield is what actually got filled). Once a real Fill Size is
        // recorded, cost is prorated by *actual* yield instead, which is
        // what makes this correct for real production loss.
        $actualCostPerJar = ($fillSizeG !== null && $yieldGrams > 0)
            ? ($bufferedCost / $yieldGrams) * $fillSizeG
            : $bufferedCost;

        $sellPrice = $recipe->sell_price !== null ? (float) $recipe->sell_price : null;
        $foodCostPercent = ($sellPrice !== null && $sellPrice > 0)
            ? round(($actualCostPerJar / $sellPrice) * 100, 2)
            : null;

        return [
            'raw_cost' => round($rawCost, 4),
            'yield_grams' => round($yieldGrams, 2),
            'buffered_cost' => round($bufferedCost, 4),
            'actual_cost_per_jar' => round($actualCostPerJar, 4),
            'food_cost_percent' => $foodCostPercent,
            'any_stale' => $anyStale,
            'any_missing' => $anyMissing,
            'ingredient_breakdown' => $ingredientBreakdown,
        ];
    }
}
