<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Support\Collection;

/**
 * Everything needed to make one unit of a recipe, laid out as a production
 * sheet: the house-made ingredients to prep first (each with what one kg of
 * it is made from), then what to mix, then the packaging. All per unit --
 * the recipe page's batch calculator and a production run's recipe sheet
 * multiply by however many units they're showing.
 */
class BuildRecipeSheet
{
    public function __construct(
        private readonly ExpandIngredientRequirements $expandIngredientRequirements = new ExpandIngredientRequirements,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     fill_size_g: float|null,
     *     recipe_grams: float,
     *     prep: array<int, array{id: int, name: string, quantity: float, cook_down_percent: float|null, components_per_kg: array}>,
     *     mix: array<int, array{id: int, name: string, unit_type: string, is_house_made: bool, quantity: float}>,
     *     pack: array<int, array{id: int, name: string, unit_type: string, quantity: float}>,
     * }
     */
    public function handle(Recipe $recipe): array
    {
        $recipe->loadMissing('mainIngredients.components');

        $lines = self::inLineOrder($recipe->mainIngredients, self::pivotLine(...));

        // Every house-made ingredient this recipe needs, nested ones
        // included, with how much of each one unit takes.
        $prep = $this->expandIngredientRequirements->handle($lines->map(fn (Ingredient $ingredient) => [
            'ingredient' => $ingredient,
            'quantity' => (float) $ingredient->pivot->quantity_per_jar,
        ]))['prep'];

        $line = fn (Ingredient $ingredient) => [
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            'unit_type' => $ingredient->unit_type,
            'is_house_made' => $ingredient->is_house_made,
            'quantity' => (float) $ingredient->pivot->quantity_per_jar,
        ];

        return [
            'name' => $recipe->name,
            'fill_size_g' => $recipe->fill_size_g !== null ? (float) $recipe->fill_size_g : null,
            'recipe_grams' => (float) $recipe->mainIngredients
                ->filter(fn (Ingredient $ingredient) => $ingredient->isGramBased())
                ->sum(fn (Ingredient $ingredient) => (float) $ingredient->pivot->quantity_per_jar),
            'prep' => self::inLineOrder(collect($prep), fn (array $entry) => [$entry['ingredient'], $entry['quantity']])
                ->map(fn (array $entry) => [
                    'id' => $entry['ingredient']->id,
                    'name' => $entry['ingredient']->name,
                    'quantity' => round($entry['quantity'], 3),
                    'cook_down_percent' => $entry['ingredient']->cook_down_percent !== null ? (float) $entry['ingredient']->cook_down_percent : null,
                    'components_per_kg' => $entry['ingredient']->componentsPerKg(),
                ])->values()->all(),
            'mix' => $lines->reject(fn (Ingredient $ingredient) => $ingredient->isPackaging())->map($line)->values()->all(),
            'pack' => $lines->filter(fn (Ingredient $ingredient) => $ingredient->isPackaging())->map($line)->values()->all(),
        ];
    }

    /**
     * Recipe line order (Show, the batch calculator, Edit's opening order):
     * food first, heaviest per-unit quantity first, then packaging grouped
     * at the bottom; name breaks ties.
     *
     * @param  callable(mixed): array{0: Ingredient, 1: float}  $line  [ingredient, quantity] for an item
     */
    public static function inLineOrder(Collection $items, callable $line): Collection
    {
        return $items->sort(function ($a, $b) use ($line) {
            [$ingredientA, $quantityA] = $line($a);
            [$ingredientB, $quantityB] = $line($b);

            return [$ingredientA->isPackaging(), (float) $quantityB, $ingredientA->name]
                <=> [$ingredientB->isPackaging(), (float) $quantityA, $ingredientB->name];
        })->values();
    }

    /** @return array{0: Ingredient, 1: float} */
    public static function pivotLine(Ingredient $ingredient): array
    {
        return [$ingredient, (float) $ingredient->pivot->quantity_per_jar];
    }
}
