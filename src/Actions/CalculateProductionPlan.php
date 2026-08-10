<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\ProductionRun;

/**
 * Ports the original spreadsheet's Production Planner "shopping list"
 * (Required / On Hand / To Purchase / Units to Buy / Purchase Unit /
 * Best Source / Eff. $/kg / Est. Cost / Prices as of) and the Purchase
 * Order tab (same list, filtered to items that need buying).
 *
 * Deliberately plain arithmetic + array building -- this is exactly the
 * kind of shopping-list math that's fine as native SQL/formulas in most
 * apps, but here it needs to combine data from three tables (recipes,
 * inventory, and the ingredient costing lookup above) per production run,
 * so it lives in one Action rather than being spread across accessors.
 */
class CalculateProductionPlan
{
    public function __construct(
        private readonly CalculateIngredientCosting $calculateIngredientCosting,
    ) {}

    /**
     * @return array{
     *     rows: array<int, array>,
     *     purchase_rows: array<int, array>,
     *     total_units: int,
     *     total_estimated_cost: float,
     * }
     */
    public function handle(ProductionRun $productionRun): array
    {
        // 'priceHistory.ingredient' is required, not redundant: it feeds
        // CalculateIngredientCosting per ingredient below, and
        // PriceHistoryEntry::price_per_unit lazy-loads $this->ingredient
        // internally if it isn't eager-loaded here, which throws under
        // this app's Model::preventLazyLoading() outside production.
        $productionRun->loadMissing([
            'recipes.ingredients.inventory',
            'recipes.ingredients.packageSizes',
            'recipes.ingredients.priceHistory.ingredient',
        ]);

        /** @var array<int, array{ingredient: Ingredient, required: float}> $requiredByIngredient */
        $requiredByIngredient = [];

        foreach ($productionRun->recipes as $recipe) {
            $units = $productionRun->batch_size * (int) $recipe->pivot->batches;
            if ($units <= 0) {
                continue;
            }

            foreach ($recipe->ingredients as $ingredient) {
                // Byproducts (e.g. pickle juice) are free and always
                // assumed sufficient -- no cost/inventory tracking, so they
                // never enter the shopping-list calculation. Recipe
                // documentation only.
                if ($ingredient->pivot->is_byproduct) {
                    continue;
                }

                $qtyPerJar = (float) $ingredient->pivot->quantity_per_jar;
                if ($qtyPerJar <= 0) {
                    continue;
                }

                $id = $ingredient->id;
                $requiredByIngredient[$id] ??= ['ingredient' => $ingredient, 'required' => 0.0];
                $requiredByIngredient[$id]['required'] += $qtyPerJar * $units;
            }
        }

        $rows = [];
        $totalCost = 0.0;

        foreach ($requiredByIngredient as $entry) {
            $ingredient = $entry['ingredient'];
            $required = $entry['required'];
            $onHand = (float) ($ingredient->inventory->on_hand ?? 0.0);
            $toPurchase = max(0.0, $required - $onHand);

            $costing = $this->calculateIngredientCosting->handle($ingredient);

            $purchaseSize = $costing['purchase_size'] > 0 ? $costing['purchase_size'] : 1.0;
            $unitsPerCase = max(1, (int) ($costing['units_per_case'] ?? 1));

            // Individual packages needed to cover the shortfall, then
            // rounded up to a whole number of CASES -- some sources are
            // only orderable in multiples (e.g. GFS beef stock concentrate:
            // 946g packages, only sold in cases of 4), so buying "5
            // packages' worth" really means buying 2 whole cases (8). When
            // units_per_case is 1 (the common case) this collapses to
            // exactly the old unitsToBuy = ceil(toPurchase / purchaseSize).
            $individualUnitsNeeded = $toPurchase > 0 ? (int) ceil($toPurchase / $purchaseSize) : 0;
            $casesNeeded = $individualUnitsNeeded > 0 ? (int) ceil($individualUnitsNeeded / $unitsPerCase) : 0;
            $unitsToBuy = $casesNeeded * $unitsPerCase;

            // The real total quantity that will actually be ordered/received
            // -- always a whole multiple of the package size (e.g. 1 case of
            // 50 = 50, not the 20-unit shortfall that triggered buying it).
            // Estimated cost is priced against this, not the raw shortfall,
            // since that's genuinely what you'll be charged for.
            $purchaseQty = $unitsToBuy * $purchaseSize;

            $estCost = 0.0;
            if ($purchaseQty > 0 && $costing['effective_price'] !== null) {
                $estCost = $ingredient->isGramBased()
                    ? $costing['effective_price'] * $purchaseQty / 1000
                    : $costing['effective_price'] * $purchaseQty;
            }

            $rows[] = [
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->name,
                'unit_type' => $ingredient->unit_type,
                'required' => round($required, 2),
                'on_hand' => round($onHand, 2),
                'to_purchase' => round($toPurchase, 2),
                'units_to_buy' => $unitsToBuy,
                'purchase_qty' => round($purchaseQty, 2),
                'purchase_unit' => $costing['purchase_unit'],
                'best_source' => $costing['source_used'],
                'effective_price' => $costing['effective_price'],
                'est_cost' => round($estCost, 2),
                'prices_as_of' => $costing['last_price_date'],
                'needs_purchase' => $toPurchase > 0,
            ];

            $totalCost += $estCost;
        }

        usort($rows, fn (array $a, array $b) => strcmp($a['ingredient_name'], $b['ingredient_name']));

        return [
            'rows' => $rows,
            'purchase_rows' => array_values(array_filter($rows, fn (array $r) => $r['needs_purchase'])),
            'total_units' => $productionRun->totalUnits(),
            'total_estimated_cost' => round($totalCost, 2),
        ];
    }
}
