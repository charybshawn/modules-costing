<?php

namespace Cultpantry\Costing\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds the module with its current real working data (ingredients,
 * sources/inventory, price history, recipes, and production plans) --
 * refreshed periodically from the live dev database rather than kept as a
 * one-time snapshot, so a fresh install (migrate:fresh --module) reproduces
 * today's actual working set. Source data lives in database/seeders/data/
 * as CSV files, one per domain seeder below, rather than inline PHP arrays.
 *
 * Kitchen Rentals are deliberately not seeded here -- that data is meant to
 * come from a real FoodCorridor CSV import, not fabricated sample bookings.
 *
 * Run with:
 *   php artisan db:seed --class="Cultpantry\\Costing\\Database\\Seeders\\CostingDatabaseSeeder"
 */
class CostingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters: Source creates each PackageSize row (package size
        // + case-lot info only); Inventory then updates quantity_on_hand on
        // those same rows; PriceHistory looks sources up by
        // ingredient+provider+brand, so it must come after both.
        $this->call([
            IngredientSeeder::class,
            SourceSeeder::class,
            InventorySeeder::class,
            PriceHistorySeeder::class,
            RecipeSeeder::class,
            ProductionPlanSeeder::class,
        ]);
    }
}
