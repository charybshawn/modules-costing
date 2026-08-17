<?php

namespace Cultpantry\Costing\Database\Seeders;

use Cultpantry\Costing\Database\Seeders\Concerns\ReadsCsv;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    use ReadsCsv;

    public function run(): void
    {
        $recipeIds = [];
        foreach ($this->readCsv('recipes.csv') as $row) {
            $recipe = Recipe::updateOrCreate(
                ['name' => $row['name']],
                [
                    'sell_price' => $this->csvFloat($row['sell_price']),
                    'fill_size_g' => $this->csvFloat($row['fill_size_g']),
                    'cost_buffer_percent' => $this->csvFloat($row['cost_buffer_percent']),
                    'notes' => $this->csvNullable($row['notes']),
                ],
            );
            $recipeIds[$row['name']] = $recipe->id;
        }

        $ingredientIds = Ingredient::pluck('id', 'name');

        // Grouped by recipe, then synced -- sync (not attach) so a rerun
        // replaces the pivot exactly rather than accumulating duplicates.
        $quantitiesByRecipe = [];
        foreach ($this->readCsv('recipe_ingredients.csv') as $row) {
            $ingredientId = $ingredientIds[$row['ingredient_name']] ?? null;
            if ($ingredientId === null) {
                continue;
            }
            $quantitiesByRecipe[$row['recipe_name']][$ingredientId] = ['quantity_per_jar' => (float) $row['quantity_per_jar']];
        }

        foreach ($quantitiesByRecipe as $recipeName => $syncData) {
            $recipeId = $recipeIds[$recipeName] ?? null;
            if ($recipeId === null) {
                continue;
            }
            Recipe::find($recipeId)->ingredients()->sync($syncData);
        }
    }
}
