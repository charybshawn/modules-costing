<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;

/**
 * Costs a house-made ingredient (e.g. apple butter) from what it's made of,
 * instead of from logged prices: one prep batch's component cost, divided
 * by the grams that batch yields after cooking. Returns the exact array
 * shape CalculateIngredientCosting does, so every recipe, plan and
 * dashboard caller treats it like any other ingredient.
 *
 * Fresh only when every component has a fresh price; otherwise the stale_*
 * fields carry the estimate from whatever each component last cost, the
 * same "last known price, needs rechecking" fallback bought ingredients
 * get. A component with no price at all leaves the whole thing unpriced.
 * Waste % is ignored -- the yield already accounts for cooking loss.
 */
class CalculatePreparedIngredientCosting
{
    /** Nesting deeper than this is treated as unpriced (validation keeps real data shallower). */
    public const MAX_DEPTH = 5;

    /**
     * @param array<int, true> $visiting house-made ids already on this path -- a cycle comes back unpriced
     */
    public function handle(Ingredient $ingredient, CalculateIngredientCosting $calculateIngredientCosting, array $visiting = []): array
    {
        $ingredient->loadMissing('components.priceHistory.ingredient', 'components.inventory', 'components.packageSizes');

        $yield = (float) $ingredient->yield_g;
        $breakdown = $this->batchCost($ingredient, $calculateIngredientCosting, $visiting + [$ingredient->id => true]);

        $priced = $yield > 0 && $ingredient->components->isNotEmpty() && !$breakdown['any_missing'];
        $perKg = $priced ? round($breakdown['cost'] / $yield * 1000, 4) : null;
        $fresh = $priced && !$breakdown['any_stale'];

        $yieldLabel = rtrim(rtrim(number_format($yield, 2), '0'), '.');

        return [
            'weekly_price' => $fresh ? $perKg : null,
            'effective_price' => $fresh ? $perKg : null,
            'source_used' => $fresh ? 'In-house' : null,
            'last_price_date' => null,
            'purchase_size' => $yield > 0 ? $yield : 1.0,
            'purchase_unit' => $yield > 0 ? "1 prep batch ({$yieldLabel} g, In-house)" : null,
            'units_per_case' => 1,
            'status' => $fresh ? 'ok' : 'no_price_this_week',
            'stale_price' => $fresh ? null : $perKg,
            'stale_effective_price' => $fresh ? null : $perKg,
            'stale_source' => $fresh || $perKg === null ? null : 'In-house',
            'stale_brand' => null,
            'stale_price_date' => null,
            'price_per_100g' => $fresh ? $ingredient->pricePer100g($perKg) : null,
            'stale_price_per_100g' => $fresh ? null : $ingredient->pricePer100g($perKg),
        ];
    }

    /**
     * One prep batch's component cost, using each component's fresh price
     * or, failing that, its stale one.
     *
     * @param array<int, true> $visiting
     * @return array{cost: float, any_stale: bool, any_missing: bool}
     */
    private function batchCost(Ingredient $ingredient, CalculateIngredientCosting $calculateIngredientCosting, array $visiting): array
    {
        $cost = 0.0;
        $anyStale = false;
        $anyMissing = false;

        foreach ($ingredient->components as $component) {
            $qty = (float) $component->pivot->quantity;
            if ($qty <= 0) {
                continue;
            }

            if ($component->is_house_made && (isset($visiting[$component->id]) || count($visiting) >= self::MAX_DEPTH)) {
                $anyMissing = true;
                continue;
            }

            $costing = $component->is_house_made
                ? $this->handle($component, $calculateIngredientCosting, $visiting)
                : $calculateIngredientCosting->handle($component);

            $price = $costing['status'] === 'ok' ? $costing['effective_price'] : $costing['stale_effective_price'];
            if ($price === null) {
                $anyMissing = true;
                continue;
            }

            if ($costing['status'] !== 'ok') {
                $anyStale = true;
            }

            $cost += $component->isGramBased() ? $qty * $price / 1000 : $qty * $price;
        }

        return ['cost' => $cost, 'any_stale' => $anyStale, 'any_missing' => $anyMissing];
    }
}
