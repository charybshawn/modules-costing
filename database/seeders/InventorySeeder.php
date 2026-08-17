<?php

namespace Cultpantry\Costing\Database\Seeders;

use Cultpantry\Costing\Database\Seeders\Concerns\ReadsCsv;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Illuminate\Database\Seeder;

/**
 * Current on-hand quantity per source -- runs after SourceSeeder, which
 * owns creating the (ingredient, provider, brand) rows this just updates.
 */
class InventorySeeder extends Seeder
{
    use ReadsCsv;

    public function run(): void
    {
        $ingredientIds = Ingredient::pluck('id', 'name');

        foreach ($this->readCsv('inventory.csv') as $row) {
            $ingredientId = $ingredientIds[$row['ingredient_name']] ?? null;
            if ($ingredientId === null) {
                continue;
            }

            PackageSize::where('ingredient_id', $ingredientId)
                ->where('provider', $row['provider'])
                ->where('brand', $this->csvNullable($row['brand']))
                ->update(['quantity_on_hand' => (float) $row['quantity_on_hand']]);
        }
    }
}
