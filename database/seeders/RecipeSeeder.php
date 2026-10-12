<?php

namespace Cultpantry\Costing\Database\Seeders;

use Cultpantry\Costing\Database\Seeders\Concerns\ReadsCsv;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Models\RecipeSize;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    use ReadsCsv;

    public function run(): void
    {
        $recipeIds = [];
        $sizeIds = [];
        foreach ($this->readCsv('recipes.csv') as $row) {
            $recipe = Recipe::updateOrCreate(
                ['name' => $row['name']],
                [
                    'preferred_batch_size' => $this->csvInt($row['preferred_batch_size']),
                    'cost_buffer_percent' => $this->csvFloat($row['cost_buffer_percent']),
                    'notes' => $this->csvNullable($row['notes']),
                ],
            );
            $recipeIds[$row['name']] = $recipe->id;

            // Every recipe starts with one size at its CSV fill weight. No
            // product is linked here -- that is specific to each install.
            $fill = $this->csvFloat($row['fill_size_g']) ?? Recipe::DEFAULT_FILL_SIZE_G;
            $sizeIds[$row['name']] = RecipeSize::updateOrCreate(
                ['recipe_id' => $recipe->id, 'fill_size_g' => $fill],
                ['label' => rtrim(rtrim(number_format($fill, 2, '.', ''), '0'), '.').'g', 'sort_order' => 0],
            )->id;
        }

        $ingredients = Ingredient::get()->keyBy('name');

        // Grouped by recipe, then synced -- sync (not attach) so a rerun
        // replaces the pivot exactly rather than accumulating duplicates.
        // Packaging lines go to the recipe's size, not its mix.
        $quantitiesByRecipe = [];
        $packagingByRecipe = [];
        foreach ($this->readCsv('recipe_ingredients.csv') as $row) {
            $ingredient = $ingredients[$row['ingredient_name']] ?? null;
            if ($ingredient === null) {
                continue;
            }
            if ($ingredient->isPackaging()) {
                $packagingByRecipe[$row['recipe_name']][$ingredient->id] = ['quantity_per_unit' => (float) $row['quantity_per_jar']];
            } else {
                $quantitiesByRecipe[$row['recipe_name']][$ingredient->id] = ['quantity_per_jar' => (float) $row['quantity_per_jar']];
            }
        }

        foreach ($packagingByRecipe as $recipeName => $syncData) {
            if (isset($sizeIds[$recipeName])) {
                RecipeSize::find($sizeIds[$recipeName])->packaging()->sync($syncData);
            }
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
