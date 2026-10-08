<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;

/**
 * Turns "this much of each ingredient" into what actually has to be bought
 * and prepped: every house-made ingredient (e.g. apple butter) is swapped
 * for its components, scaled by how many prep batches the amount needs
 * (quantity / its cooked yield), recursively for components that are house-made
 * themselves. Bought ingredients pass straight through, merged by id.
 *
 * House-made ingredients aren't stock-tracked yet, so none of their
 * requirement is assumed to be on hand -- it's all prepped for the job.
 */
class ExpandIngredientRequirements
{
    /**
     * @param iterable<array{ingredient: Ingredient, quantity: float}> $requirements
     * @return array{
     *     raw: array<int, array{ingredient: Ingredient, quantity: float}>,
     *     prep: array<int, array{ingredient: Ingredient, quantity: float, batches: float}>,
     * }
     */
    public function handle(iterable $requirements): array
    {
        $result = ['raw' => [], 'prep' => []];

        foreach ($requirements as $requirement) {
            $this->add($result, $requirement['ingredient'], (float) $requirement['quantity'], []);
        }

        return $result;
    }

    /**
     * @param array<int, true> $visiting house-made ids already on this path
     */
    private function add(array &$result, Ingredient $ingredient, float $quantity, array $visiting): void
    {
        if ($quantity <= 0) {
            return;
        }

        $yield = (float) $ingredient->yieldGrams();

        // Bought -- or a house-made one that can't be expanded (no yield,
        // a cycle, too deep), which is left as-is rather than dropped.
        if (!$ingredient->is_house_made || $yield <= 0 || isset($visiting[$ingredient->id])
            || count($visiting) >= CalculatePreparedIngredientCosting::MAX_DEPTH) {
            $result['raw'][$ingredient->id] ??= ['ingredient' => $ingredient, 'quantity' => 0.0];
            $result['raw'][$ingredient->id]['quantity'] += $quantity;

            return;
        }

        $result['prep'][$ingredient->id] ??= ['ingredient' => $ingredient, 'quantity' => 0.0, 'batches' => 0.0];
        $result['prep'][$ingredient->id]['quantity'] += $quantity;
        $result['prep'][$ingredient->id]['batches'] += $quantity / $yield;

        // Everything a plan row reads off a bought ingredient.
        $ingredient->loadMissing('components.inventory', 'components.packageSizes', 'components.priceHistory.ingredient');

        foreach ($ingredient->components as $component) {
            $this->add($result, $component, (float) $component->pivot->quantity * $quantity / $yield, $visiting + [$ingredient->id => true]);
        }
    }
}
