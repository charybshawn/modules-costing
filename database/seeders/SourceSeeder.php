<?php

namespace Cultpantry\Costing\Database\Seeders;

use Cultpantry\Costing\Database\Seeders\Concerns\ReadsCsv;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Illuminate\Database\Seeder;

/**
 * Every real source (provider/brand) known for each ingredient -- package
 * size and case-lot purchasing info only. Current on-hand quantity is a
 * separate concern, seeded onto these same rows by InventorySeeder, which
 * must run after this one.
 */
class SourceSeeder extends Seeder
{
    use ReadsCsv;

    public function run(): void
    {
        $ingredientIds = Ingredient::pluck('id', 'name');

        foreach ($this->readCsv('sources.csv') as $row) {
            $ingredientId = $ingredientIds[$row['ingredient_name']] ?? null;
            if ($ingredientId === null) {
                continue;
            }

            PackageSize::updateOrCreate(
                [
                    'ingredient_id' => $ingredientId,
                    'provider' => $row['provider'],
                    'brand' => $this->csvNullable($row['brand']),
                ],
                [
                    'package_size' => (float) $row['package_size'],
                    'units_per_case' => (int) $row['units_per_case'],
                ],
            );
        }
    }
}
