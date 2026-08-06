<?php

namespace Cultpantry\Costing\Database\Seeders;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the module with the real data from the original "Cult Pantry —
 * Costing & Recipe Workbook" Google Sheet, so the module launches with
 * working data instead of empty tables.
 *
 * Price History dates are stored as "days ago from today" offsets (computed
 * from the sheet's original dates relative to when it was read) rather than
 * fixed calendar dates. This means the 7-day "price this week" window
 * behaves the same way it did in the original sheet no matter what date you
 * actually run this seeder -- e.g. Cream Cheese's most recent entry always
 * lands ~1 day ago (inside the window, so it prices normally), while
 * Caramelized Onions / Dried Cranberries' most recent entries land ~24 days
 * ago (outside the window, so they correctly show "no price this week"
 * until you log a fresh price). This is faithful to the original workbook's
 * behavior, not a seeding quirk -- add a new Price History row dated today
 * for any ingredient to see it price immediately.
 *
 * Run with:
 *   php artisan db:seed --class="Cultpantry\\Costing\\Database\\Seeders\\CostingDatabaseSeeder"
 */
class CostingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = $this->seedIngredients();
        $this->seedInventory($ingredients);
        $recipes = $this->seedRecipes($ingredients);
        $this->seedProductionRun($recipes);
    }

    /**
     * @return array<string, Ingredient>
     */
    private function seedIngredients(): array
    {
        $definitions = [
            ['name' => 'Cream Cheese', 'category' => 'Dairy & Eggs', 'unit_type' => 'g', 'waste_percent' => 100, 'preferred_source' => 'GFS'],
            ['name' => 'Swiss Cheese', 'category' => 'Dairy & Eggs', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => 'Cheddar Cheese', 'category' => 'Dairy & Eggs', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => 'Bacon Bits', 'category' => 'Meat', 'unit_type' => 'g', 'waste_percent' => 96],
            ['name' => 'Sriracha', 'category' => 'Wet', 'unit_type' => 'g', 'waste_percent' => 95],
            ['name' => 'Maple Syrup', 'category' => 'Wet', 'unit_type' => 'g', 'waste_percent' => 95],
            ['name' => 'Pickles', 'category' => 'Wet', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => 'Orange Juice Concentrate', 'category' => 'Wet', 'unit_type' => 'g', 'waste_percent' => 95],
            ['name' => 'Beef Stock Concentrate', 'category' => 'Wet', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => 'Caramelized Onions', 'category' => 'Vegetables & Fruit', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => 'Orange Zest', 'category' => 'Vegetables & Fruit', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => 'Dried Cranberries', 'category' => 'Baking', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => 'Dried Dill', 'category' => 'Spices', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => 'Pickled Jalapenos', 'category' => 'Spices', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => 'Salt', 'category' => 'Spices', 'unit_type' => 'g', 'waste_percent' => 100],
            ['name' => '8oz Deli Cup', 'category' => 'Packaging', 'unit_type' => 'unit', 'waste_percent' => 100],
            ['name' => 'Orange Extract', 'category' => 'Vegetables & Fruit', 'unit_type' => 'g', 'waste_percent' => 100],
        ];

        /** @var array<string, Ingredient> $ingredients */
        $ingredients = [];
        foreach ($definitions as $definition) {
            $ingredients[$definition['name']] = Ingredient::updateOrCreate(
                ['name' => $definition['name']],
                $definition
            );
        }

        // Price History: [ingredient, days_ago (or null), provider, qty, total_price, sku, notes]
        $priceHistory = [
            ['Cream Cheese', 84, 'GFS', 15000, 151.62, null, 'GFS Cream Cheese'],
            ['Cream Cheese', 245, 'GFS', 15000, 144.09, null, 'GFS Cream Cheese'],
            ['Cream Cheese', 245, 'GFS', 20000, 246.00, '10068100015406', 'Philadelphia Industrial Cream Cheese'],
            ['Cream Cheese', 264, 'GFS', 20000, 254.00, '10068100015406', 'Philadelphia Industrial Cream Cheese'],
            ['Cream Cheese', 1, 'GFS', 20000, 251.72, null, 'GFS Cream Cheese'],
            ['Bacon Bits', 104, 'Wholesale Club', 567, 19.00, null, 'Pre-cooked bacon bits'],
            ['Sriracha', null, 'GFS', null, null, null, null],
            ['Maple Syrup', 104, 'No Frills', 1000, 13.50, null, '1L bottle'],
            ['Maple Syrup', 104, 'No Frills', 500, 13.50, null, '500ml bottle'],
            ['Pickles', 245, 'GFS', 19000, 110.79, '1445501', "Brickman's 19L pale"],
            ['Pickles', 245, 'GFS', 8000, 42.49, '2606805', "Bick's Mini Dill"],
            ['Pickles', 264, 'Wholesale Club', 4000, 21.99, null, null],
            ['Orange Juice Concentrate', 104, 'GFS', 10500, 263.74, null, null],
            ['Beef Stock Concentrate', 104, 'GFS', 3784, 96.76, null, 'Knorr Professional 946ml'],
            ['Beef Stock Concentrate', 104, 'Amazon', 946, 32.99, null, 'Knorr Professional 946ml'],
            ['Caramelized Onions', 104, 'GFS', 1816, 41.96, null, null],
            ['Caramelized Onions', 24, 'GFS', 1816, 40.09, null, null],
            ['Orange Zest', null, null, null, null, null, 'Fresh — cost negligible'],
            ['Dried Cranberries', 104, 'GFS', 6000, 49.90, null, null],
            ['Dried Cranberries', 24, 'GFS', 6000, 49.90, null, null],
            ['Dried Cranberries', 24, 'Wholesale Club', 1360, 10.99, null, null],
            ['Dried Dill', 84, 'Shuswap Health Food', 100, 7.70, null, null],
            ['Pickled Jalapenos', 84, 'GFS', 8000, 51.75, null, 'Gordon Choice Sliced Jalapeno Peppers'],
            ['Cheddar Cheese', 104, 'No Frills', 2270, 28.99, null, null],
            ['Cheddar Cheese', 104, 'GFS', 4540, 87.69, null, null],
            ['Swiss Cheese', 104, 'No Frills', 1500, 25.99, null, null],
            ['Salt', 104, 'No Frills', 20000, 8.99, null, 'Table salt'],
            ['8oz Deli Cup', null, 'GFS', null, null, null, 'Per unit (not per kg)'],
        ];

        foreach ($priceHistory as [$name, $daysAgo, $provider, $qty, $totalPrice, $sku, $notes]) {
            $ingredients[$name]->priceHistory()->create([
                'purchased_at' => $daysAgo === null ? null : Carbon::now()->subDays($daysAgo)->toDateString(),
                'provider' => $provider,
                'qty' => $qty,
                'total_price' => $totalPrice,
                'sku' => $sku,
                'notes' => $notes,
            ]);
        }

        return $ingredients;
    }

    /**
     * @param array<string, Ingredient> $ingredients
     */
    private function seedInventory(array $ingredients): void
    {
        // [ingredient, unit_size (g or units), units_on_hand]
        $inventory = [
            ['Cream Cheese', 20000, 0],
            ['Swiss Cheese', 2840, 1.3],
            ['Cheddar Cheese', 25000, 2.2],
            ['Bacon Bits', 567, 1],
            ['Sriracha', 1000, 0],
            ['Maple Syrup', 1000, 2.5],
            ['Pickles', 3870, 8],
            ['Orange Juice Concentrate', 1000, 3],
            ['Beef Stock Concentrate', 946, 3.75],
            ['Caramelized Onions', 908, 3],
            ['Orange Zest', 0, 0],
            ['Dried Cranberries', 1360, 13.2],
            ['Dried Dill', 1800, 1],
            ['Pickled Jalapenos', 4000, 6.8],
            ['Salt', 750, 1],
            ['8oz Deli Cup', 0, 0],
            ['Orange Extract', 59, 1],
        ];

        foreach ($inventory as [$name, $unitSize, $unitsOnHand]) {
            $ingredients[$name]->inventory()->update([
                'unit_size' => $unitSize,
                'units_on_hand' => $unitsOnHand,
            ]);
        }
    }

    /**
     * @param array<string, Ingredient> $ingredients
     * @return array<string, Recipe>
     */
    private function seedRecipes(array $ingredients): array
    {
        // [flavour name => [ingredient name => grams (or units) per jar]]
        $definitions = [
            'Sriracha Maple Bacon' => [
                'Cream Cheese' => 250,
                'Sriracha' => 30,
                'Maple Syrup' => 15,
                'Bacon Bits' => 35,
                '8oz Deli Cup' => 1,
            ],
            'Dangerously Dilly' => [
                'Cream Cheese' => 250,
                'Pickles' => 50,
                'Dried Dill' => 2.5,
                '8oz Deli Cup' => 1,
            ],
            'Zesty Orange Cranberry' => [
                'Cream Cheese' => 250,
                'Orange Juice Concentrate' => 34,
                'Orange Zest' => 4,
                'Dried Cranberries' => 50,
                '8oz Deli Cup' => 1,
            ],
            'Caramelized French Onion' => [
                'Cream Cheese' => 250,
                'Caramelized Onions' => 45.35,
                'Beef Stock Concentrate' => 5,
                'Swiss Cheese' => 20,
                '8oz Deli Cup' => 1,
            ],
            'Jalapeno Popper' => [
                'Cream Cheese' => 250,
                'Pickled Jalapenos' => 43.75,
                'Cheddar Cheese' => 20,
                'Salt' => 1,
                '8oz Deli Cup' => 1,
            ],
        ];

        /** @var array<string, Recipe> $recipes */
        $recipes = [];
        foreach ($definitions as $name => $ingredientQuantities) {
            $recipe = Recipe::updateOrCreate(['name' => $name]);

            $syncData = [];
            foreach ($ingredientQuantities as $ingredientName => $quantityPerJar) {
                $syncData[$ingredients[$ingredientName]->id] = ['quantity_per_jar' => $quantityPerJar];
            }
            $recipe->ingredients()->sync($syncData);

            $recipes[$name] = $recipe;
        }

        return $recipes;
    }

    /**
     * @param array<string, Recipe> $recipes
     */
    private function seedProductionRun(array $recipes): void
    {
        // batch_size 1 keeps these numbers meaning the same real jar
        // counts the original demo data always represented.
        $run = ProductionRun::create([
            'name' => 'Demo production run (seeded from the original sheet)',
            'batch_size' => 1,
            'run_date' => Carbon::now()->toDateString(),
        ]);

        $unitsPerFlavour = [
            'Sriracha Maple Bacon' => 60,
            'Dangerously Dilly' => 0,
            'Zesty Orange Cranberry' => 80,
            'Caramelized French Onion' => 0,
            'Jalapeno Popper' => 40,
        ];

        $syncData = [];
        foreach ($unitsPerFlavour as $name => $units) {
            $syncData[$recipes[$name]->id] = ['batches' => $units];
        }
        $run->recipes()->sync($syncData);
    }
}
