<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Models\RecipeSize;

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
     * Costed for one size: the mix is prorated to that size's fill weight and
     * that size's own packaging is added per unit. With no size given, the
     * recipe's first size is used; a recipe with no sizes yet is costed as
     * the bare mix (no fill weight, no packaging, no price).
     *
     * @return array{
     *     recipe_size_id: int|null,
     *     fill_size_g: float|null,
     *     packaging_cost: float,
     *     sell_price: float|null,
     *     profit_per_unit: float|null,
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
    public function handle(Recipe $recipe, ?RecipeSize $size = null): array
    {
        // 'priceHistory.ingredient' required: PriceHistoryEntry::price_per_unit
        // reads $this->ingredient internally, and without this eager load
        // that lazy-loads per entry -- throws under this app's
        // Model::preventLazyLoading() outside production. 'inventory' and
        // 'packageSizes' avoid an N+1 inside CalculateIngredientCosting
        // (unused by this Action's own output, but still read per ingredient).
        $recipe->loadMissing(
            'mainIngredients.priceHistory.ingredient', 'mainIngredients.inventory', 'mainIngredients.packageSizes',
            'sizes.packaging.priceHistory.ingredient', 'sizes.packaging.inventory', 'sizes.packaging.packageSizes',
        );
        $size ??= $recipe->sizes->first();
        // A size handed in directly may not have come through the recipe's eager loads.
        $size?->loadMissing('packaging.priceHistory.ingredient', 'packaging.inventory', 'packaging.packageSizes');

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

        // The size's packaging, per filled unit. Costed like any ingredient
        // (same price source, same stale/missing handling) but kept out of
        // the mix cost, because it scales per unit, not with the fill weight.
        $packagingCost = 0.0;
        if ($size) {
            foreach ($size->packaging as $ingredient) {
                $qty = (float) $ingredient->pivot->quantity_per_unit;
                if ($qty <= 0) {
                    continue;
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
                        'is_packaging' => true,
                    ];
                    continue;
                }

                if ($costing['status'] !== 'ok') {
                    $anyStale = true;
                }

                // Packaging is counted in units; a gram-priced item (rare)
                // is still priced per kg like everywhere else.
                $costContribution = $ingredient->isGramBased()
                    ? ($qty * $effectivePrice / 1000)
                    : ($qty * $effectivePrice);

                $packagingCost += $costContribution;

                $ingredientBreakdown[] = [
                    'ingredient_id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'unit_type' => $ingredient->unit_type,
                    'quantity_per_jar' => $qty,
                    'effective_price' => $effectivePrice,
                    'cost_contribution' => round($costContribution, 4),
                    'status' => $costing['status'] === 'ok' ? 'ok' : 'stale',
                    'is_packaging' => true,
                ];
            }
        }

        $bufferPercent = $recipe->cost_buffer_percent !== null ? (float) $recipe->cost_buffer_percent : 0.0;
        $bufferFactor = 1 + $bufferPercent / 100;
        $bufferedMix = $rawCost * $bufferFactor;
        $bufferedPackaging = $packagingCost * $bufferFactor;

        $fillSizeG = $size ? (float) $size->fill_size_g : null;

        // No fill size yet -- the best available estimate is the buffered
        // total-batch cost (i.e. assume the theoretical yield is what got
        // filled). Once a size has a real fill weight, the mix is prorated by
        // *actual* yield, which is what makes this correct for real
        // production loss. Packaging is added after prorating: a cup costs
        // the same whatever is put in it.
        $mixPerUnit = ($fillSizeG !== null && $yieldGrams > 0)
            ? ($bufferedMix / $yieldGrams) * $fillSizeG
            : $bufferedMix;
        $actualCostPerJar = $mixPerUnit + $bufferedPackaging;

        // The price comes from the size's linked product, not from anything
        // stored here, so a price change on the product flows straight through.
        $sellPrice = $size?->sellPrice();
        $foodCostPercent = ($sellPrice !== null && $sellPrice > 0)
            ? round(($actualCostPerJar / $sellPrice) * 100, 2)
            : null;

        return [
            'recipe_size_id' => $size?->id,
            'fill_size_g' => $fillSizeG,
            'packaging_cost' => round($bufferedPackaging, 4),
            'sell_price' => $sellPrice,
            'profit_per_unit' => $sellPrice !== null ? round($sellPrice - $actualCostPerJar, 4) : null,
            'raw_cost' => round($rawCost + $packagingCost, 4),
            'yield_grams' => round($yieldGrams, 2),
            'buffered_cost' => round($bufferedMix + $bufferedPackaging, 4),
            'actual_cost_per_jar' => round($actualCostPerJar, 4),
            'food_cost_percent' => $foodCostPercent,
            'any_stale' => $anyStale,
            'any_missing' => $anyMissing,
            'ingredient_breakdown' => $ingredientBreakdown,
        ];
    }

    /**
     * Every size of a recipe costed, keyed by size id.
     *
     * @return array<int, array>
     */
    public function forSizes(Recipe $recipe): array
    {
        $recipe->loadMissing('sizes');

        $costs = [];
        foreach ($recipe->sizes as $size) {
            $costs[$size->id] = $this->handle($recipe, $size);
        }

        return $costs;
    }
}
