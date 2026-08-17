<?php

namespace Cultpantry\Costing\Database\Seeders;

use Cultpantry\Costing\Database\Seeders\Concerns\ReadsCsv;
use Cultpantry\Costing\Models\Ingredient;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    use ReadsCsv;

    public function run(): void
    {
        foreach ($this->readCsv('ingredients.csv') as $row) {
            Ingredient::updateOrCreate(
                ['name' => $row['name']],
                [
                    'category' => $this->csvNullable($row['category']),
                    'unit_type' => $row['unit_type'],
                    'waste_percent' => (float) $row['waste_percent'],
                    'preferred_source' => $this->csvNullable($row['preferred_source']),
                    'preferred_brand' => $this->csvNullable($row['preferred_brand']),
                    'low_stock_threshold' => $this->csvFloat($row['low_stock_threshold']),
                    'byproduct_name' => $this->csvNullable($row['byproduct_name']),
                    'notes' => $this->csvNullable($row['notes']),
                ],
            );
        }
    }
}
