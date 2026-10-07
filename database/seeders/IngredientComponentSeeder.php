<?php

namespace Cultpantry\Costing\Database\Seeders;

use Cultpantry\Costing\Database\Seeders\Concerns\ReadsCsv;
use Cultpantry\Costing\Models\Ingredient;
use Illuminate\Database\Seeder;

/**
 * What each house-made ingredient (e.g. Apple Butter) is made from, per
 * prep batch. Runs after IngredientSeeder -- both sides of every line must
 * already exist.
 */
class IngredientComponentSeeder extends Seeder
{
    use ReadsCsv;

    public function run(): void
    {
        $ingredientIds = Ingredient::pluck('id', 'name');

        // Grouped by house-made ingredient, then synced -- same as recipe
        // lines, so a rerun replaces rather than duplicates.
        $componentsByIngredient = [];
        foreach ($this->readCsv('ingredient_components.csv') as $row) {
            $componentId = $ingredientIds[$row['component_name']] ?? null;
            if ($componentId === null) {
                continue;
            }
            $componentsByIngredient[$row['ingredient_name']][$componentId] = ['quantity' => (float) $row['quantity']];
        }

        foreach ($componentsByIngredient as $ingredientName => $syncData) {
            $ingredientId = $ingredientIds[$ingredientName] ?? null;
            if ($ingredientId === null) {
                continue;
            }
            Ingredient::find($ingredientId)->components()->sync($syncData);
        }
    }
}
