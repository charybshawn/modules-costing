<?php

use App\Actions\UpdateSiteSetting;
use Cultpantry\Costing\Actions\CalculateIngredientCosting;
use Cultpantry\Costing\Actions\CalculateMaxProducibleUnits;
use Cultpantry\Costing\Actions\CalculateProductionPlan;
use Cultpantry\Costing\Actions\CalculateRecipeCost;
use Cultpantry\Costing\Actions\GenerateBatchCode;
use Cultpantry\Costing\Actions\GetBatchCodePrefix;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Support\Carbon;

describe('CalculateIngredientCosting', function () {
    it('picks the cheapest price within the last 7 days and ignores older entries, then waste-adjusts it', function () {
        $ingredient = Ingredient::create([
            'name' => 'Test Cream',
            'unit_type' => 'g',
            'waste_percent' => 50,
        ]);

        // Outside the 7-day window -- cheapest overall, but must be ignored.
        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(10)->toDateString(),
            'provider' => 'Cheap Old',
            'qty' => 1000,
            'total_price' => 5,
        ]);

        // Inside the window: $8/kg.
        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(3)->toDateString(),
            'provider' => 'B',
            'qty' => 1000,
            'total_price' => 8,
        ]);

        // Inside the window but more expensive: $10/kg.
        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
            'provider' => 'A',
            'qty' => 1000,
            'total_price' => 10,
        ]);

        $result = (new CalculateIngredientCosting)->handle($ingredient->fresh());

        expect($result['status'])->toBe('ok');
        expect((float) $result['weekly_price'])->toBe(8.0);
        expect($result['source_used'])->toBe('B');
        // waste_percent 50 => effective = weekly * (100/50) = weekly * 2.
        expect((float) $result['effective_price'])->toBe(16.0);
    });

    it('locks to the preferred source even when a cheaper provider exists', function () {
        $ingredient = Ingredient::create([
            'name' => 'Test Cream Preferred',
            'unit_type' => 'g',
            'waste_percent' => 100,
            'preferred_source' => 'A',
        ]);

        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(3)->toDateString(),
            'provider' => 'B',
            'qty' => 1000,
            'total_price' => 8,
        ]);

        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
            'provider' => 'A',
            'qty' => 1000,
            'total_price' => 10,
        ]);

        $result = (new CalculateIngredientCosting)->handle($ingredient->fresh());

        expect($result['source_used'])->toBe('A');
        expect((float) $result['weekly_price'])->toBe(10.0);
    });

    it('returns a clean "no price this week" status instead of an error when data is missing or stale', function () {
        $ingredient = Ingredient::create([
            'name' => 'No Price Ingredient',
            'unit_type' => 'g',
            'waste_percent' => 100,
        ]);

        // Incomplete entry (mirrors the original sheet's #DIV/0! rows).
        $ingredient->priceHistory()->create([
            'purchased_at' => null,
            'provider' => 'GFS',
            'qty' => null,
            'total_price' => null,
        ]);

        $result = (new CalculateIngredientCosting)->handle($ingredient->fresh());

        expect($result['status'])->toBe('no_price_this_week');
        expect($result['weekly_price'])->toBeNull();
        // No weight_per_unit set either -- falls back to the old
        // meaningless 1.0 default, since there's nothing better to use.
        expect($result['purchase_size'])->toBe(1.0);
        expect($result['purchase_unit'])->toBeNull();
        // Never a measurable price at all (the one entry is incomplete) --
        // nothing to display as a stale fallback either.
        expect($result['stale_price'])->toBeNull();
    });

    it('has no purchase-size fallback when there is no fresh price and no matching source, but still surfaces the stale price for display', function () {
        // The old ingredient-level weight_per_unit fallback was removed
        // (migration 2026_08_06_100008) -- every real source is now
        // expected to carry its own package size directly, and
        // resolvePackageSize() no longer falls back to anything else.
        $ingredient = Ingredient::create([
            'name' => 'Fallback Ingredient',
            'unit_type' => 'g',
            'waste_percent' => 100,
        ]);

        // Stale entry -- outside the 7-day window, so it doesn't count.
        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(30)->toDateString(),
            'provider' => 'Old Source',
            'brand' => 'Old Brand',
            'qty' => 500,
            'total_price' => 5,
        ]);

        $result = (new CalculateIngredientCosting)->handle($ingredient->fresh());

        expect($result['status'])->toBe('no_price_this_week');
        // No matching PackageSize for Old Source/Old Brand -- defaults to
        // the bare 1.0/null, same as having no source at all.
        expect($result['purchase_size'])->toBe(1.0);
        expect($result['purchase_unit'])->toBeNull();
        // weekly_price/effective_price stay null (not fresh enough to
        // trust for costing), but the stale fields surface the same old
        // entry for display: $5 / 500g * 1000 = $10/kg.
        expect($result['weekly_price'])->toBeNull();
        expect($result['stale_price'])->toBe(10.0);
        expect($result['stale_source'])->toBe('Old Source');
        expect($result['stale_brand'])->toBe('Old Brand');
        expect($result['stale_price_date'])->toBe(Carbon::now()->subDays(30)->toDateString());
    });

    it('narrows the preferred source down to a specific preferred brand when both are set', function () {
        $ingredient = Ingredient::create([
            'name' => 'Test Cream Preferred Brand',
            'unit_type' => 'g',
            'waste_percent' => 100,
            'preferred_source' => 'A',
            'preferred_brand' => 'Kraft',
        ]);

        // Same provider, wrong brand -- must be ignored even though it's
        // more recent and from the pinned provider.
        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
            'provider' => 'A',
            'brand' => 'Great Value',
            'qty' => 1000,
            'total_price' => 8,
        ]);

        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(2)->toDateString(),
            'provider' => 'A',
            'brand' => 'Kraft',
            'qty' => 1000,
            'total_price' => 10,
        ]);

        $result = (new CalculateIngredientCosting)->handle($ingredient->fresh());

        expect($result['status'])->toBe('ok');
        expect((float) $result['weekly_price'])->toBe(10.0);
        expect($result['source_used'])->toBe('A');
    });

    it('derives price_per_100g as a straight $/kg / 10 for gram-based ingredients, and leaves it null for unit-type ones', function () {
        $gramBased = Ingredient::create(['name' => 'Milk', 'unit_type' => 'g', 'waste_percent' => 100]);
        $gramBased->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
            'provider' => 'Wholesale Club',
            'qty' => 1000,
            'total_price' => 4.50, // $4.50/kg
        ]);

        $result = (new CalculateIngredientCosting)->handle($gramBased->fresh());

        expect((float) $result['weekly_price'])->toBe(4.5);
        // Weight and volume treated 1:1 -- no density conversion, just /10.
        expect($result['price_per_100g'])->toBe(0.45);

        $unitType = Ingredient::create(['name' => 'Unit Ingredient', 'unit_type' => 'unit', 'waste_percent' => 100]);
        $unitType->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
            'provider' => 'Wholesale Club',
            'qty' => 10,
            'total_price' => 5,
        ]);

        // $/100g doesn't apply to a per-unit price (e.g. packaging).
        $result = (new CalculateIngredientCosting)->handle($unitType->fresh());
        expect($result['price_per_100g'])->toBeNull();
    });

    it('ignores Price History qty for purchase_size, even for a fresh entry -- a logged price is never a reliable statement of real package size', function () {
        $ingredient = Ingredient::create([
            'name' => 'Preference Ingredient',
            'unit_type' => 'g',
            'waste_percent' => 100,
        ]);

        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
            'provider' => 'Fresh Source',
            'qty' => 2000, // a one-off 2kg purchase -- must not affect purchase_size
            'total_price' => 20,
        ]);

        // The real package size for the winning source, kept deliberately
        // different from the price entry's qty (2000) above.
        $ingredient->packageSizes()->create(['provider' => 'Fresh Source', 'package_size' => 1000]);

        $result = (new CalculateIngredientCosting)->handle($ingredient->fresh());

        expect($result['status'])->toBe('ok');
        // weekly_price still correctly reflects the fresh $20/2kg entry...
        expect($result['weekly_price'])->toBe(10.0);
        // ...but purchase_size comes from the source's real PackageSize row,
        // not the price entry's qty.
        expect($result['purchase_size'])->toBe(1000.0);
    });

    it('uses the brand-specific PackageSize matching the winning price\'s provider/brand', function () {
        $ingredient = Ingredient::create([
            'name' => 'Cream Cheese',
            'unit_type' => 'g',
            'waste_percent' => 100,
        ]);

        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDay()->toDateString(),
            'provider' => 'GFS',
            'brand' => 'Kraft',
            'qty' => 15000,
            'total_price' => 90, // cheapest -- wins
        ]);
        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDay()->toDateString(),
            'provider' => 'Wholesale Club',
            'brand' => 'Brand X',
            'qty' => 20000,
            'total_price' => 200,
        ]);

        $ingredient->packageSizes()->create(['provider' => 'GFS', 'brand' => 'Kraft', 'package_size' => 15000]);
        $ingredient->packageSizes()->create(['provider' => 'Wholesale Club', 'brand' => 'Brand X', 'package_size' => 20000]);

        $result = (new CalculateIngredientCosting)->handle($ingredient->fresh());

        expect($result['status'])->toBe('ok');
        expect($result['source_used'])->toBe('GFS');
        expect($result['purchase_size'])->toBe(15000.0);
        expect($result['purchase_unit'])->toBe('15 kg (GFS -- Kraft)');
    });

    it('follows the winning brand when it changes, rather than a fixed pick', function () {
        $ingredient = Ingredient::create([
            'name' => 'Cream Cheese Switching',
            'unit_type' => 'g',
            'waste_percent' => 100,
        ]);

        $ingredient->packageSizes()->create(['provider' => 'GFS', 'brand' => 'Kraft', 'package_size' => 15000]);
        $ingredient->packageSizes()->create(['provider' => 'Wholesale Club', 'brand' => 'Brand X', 'package_size' => 20000]);

        // Wholesale Club/Brand X is cheaper this time.
        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDay()->toDateString(),
            'provider' => 'GFS',
            'brand' => 'Kraft',
            'qty' => 15000,
            'total_price' => 150,
        ]);
        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDay()->toDateString(),
            'provider' => 'Wholesale Club',
            'brand' => 'Brand X',
            'qty' => 20000,
            'total_price' => 100,
        ]);

        $result = (new CalculateIngredientCosting)->handle($ingredient->fresh());

        expect($result['source_used'])->toBe('Wholesale Club');
        expect($result['purchase_size'])->toBe(20000.0);
        expect($result['purchase_unit'])->toBe('20 kg (Wholesale Club -- Brand X)');
    });

    it('defaults purchase_size to 1.0/no purchase_unit when the winning provider/brand has no logged package size', function () {
        // No ingredient-level fallback exists anymore (migration
        // 2026_08_06_100008 dropped weight_per_unit and
        // resolvePackageSize() no longer reads it) -- a provider/brand
        // with a real price but no PackageSize row gets the same bare
        // default as an ingredient with no sources at all.
        $ingredient = Ingredient::create([
            'name' => 'No Brand Package Size',
            'unit_type' => 'g',
            'waste_percent' => 100,
        ]);

        $ingredient->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDay()->toDateString(),
            'provider' => 'GFS',
            'brand' => 'Kraft',
            'qty' => 15000,
            'total_price' => 90,
        ]);

        $result = (new CalculateIngredientCosting)->handle($ingredient->fresh());

        expect($result['status'])->toBe('ok');
        expect($result['purchase_size'])->toBe(1.0);
        expect($result['purchase_unit'])->toBeNull();
    });
});

describe('CalculateProductionPlan', function () {
    it('computes required/on-hand/to-purchase/units-to-buy/cost per ingredient and filters the purchase-only list', function () {
        $primary = Ingredient::create(['name' => 'Plan Cream', 'unit_type' => 'g', 'waste_percent' => 100]);
        $primary->priceHistory()->create([
            'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
            'provider' => 'X',
            'qty' => 1000,
            'total_price' => 10, // $10/kg
        ]);
        // on_hand is now the sum of per-source quantity_on_hand rows
        // (costing_ingredient_package_sizes), not the old unit_size x
        // units_on_hand aggregate on costing_inventory.
        $primary->packageSizes()->create(['provider' => 'X', 'package_size' => 1000, 'quantity_on_hand' => 50]); // 50g on hand

        $covered = Ingredient::create(['name' => 'Covered Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
        $covered->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1, 'quantity_on_hand' => 200]); // 200g on hand, no price needed

        $recipe = Recipe::create(['name' => 'Plan Flavour', 'fill_size_g' => null]);
        $recipe->ingredients()->sync([
            $primary->id => ['quantity_per_jar' => 100], // 100g per jar
            $covered->id => ['quantity_per_jar' => 10],  // 10g per jar
        ]);

        $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
        $run->recipes()->sync([$recipe->id => ['batches' => 10]]);

        $plan = (new CalculateProductionPlan(new CalculateIngredientCosting))->handle($run->fresh());

        $rows = collect($plan['rows'])->keyBy('ingredient_name');

        // Required: 100g/jar x 10 units = 1000g. On hand 50g. To purchase 950g.
        expect((float) $rows['Plan Cream']['required'])->toBe(1000.0);
        expect((float) $rows['Plan Cream']['on_hand'])->toBe(50.0);
        expect((float) $rows['Plan Cream']['to_purchase'])->toBe(950.0);
        expect($rows['Plan Cream']['units_to_buy'])->toBe(1); // ceil(950/1000)
        expect($rows['Plan Cream']['needs_purchase'])->toBeTrue();
        // purchase_qty is a whole package multiple: 1 x 1000g = 1000g, not
        // the 950g raw shortfall -- you can't actually buy 950g, only whole
        // 1000g packages, so cost is estimated against what you'll really
        // be charged for. Est cost = $10/kg x 1000g / 1000 = $10.00.
        expect((float) $rows['Plan Cream']['purchase_qty'])->toBe(1000.0);
        expect((float) $rows['Plan Cream']['est_cost'])->toBe(10.0);

        // Required: 10g/jar x 10 units = 100g. On hand 200g covers it fully.
        expect((float) $rows['Covered Ingredient']['required'])->toBe(100.0);
        expect((float) $rows['Covered Ingredient']['to_purchase'])->toBe(0.0);
        expect($rows['Covered Ingredient']['needs_purchase'])->toBeFalse();
        expect((float) $rows['Covered Ingredient']['est_cost'])->toBe(0.0);

        expect($plan['total_units'])->toBe(10);
        expect((float) $plan['total_estimated_cost'])->toBe(10.0);

        // Purchase Order view only lists ingredients that actually need buying.
        $purchaseNames = collect($plan['purchase_rows'])->pluck('ingredient_name');
        expect($purchaseNames)->toContain('Plan Cream');
        expect($purchaseNames)->not->toContain('Covered Ingredient');
    });

    it('multiplies batches by the run\'s batch_size to get real units, not just the batch count', function () {
        $ingredient = Ingredient::create(['name' => 'Batch Size Plan Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
        // Zero on hand is the default with no source rows at all -- no
        // PackageSize needed to represent it.

        $recipe = Recipe::create(['name' => 'Batch Size Plan Flavour', 'fill_size_g' => null]);
        $recipe->ingredients()->sync([$ingredient->id => ['quantity_per_jar' => 10]]); // 10g/unit

        // 4 batches x batch_size 15 = 60 real units -> requires 600g, not 40g.
        $run = ProductionRun::create(['batch_size' => 15, 'run_date' => Carbon::now()->toDateString()]);
        $run->recipes()->sync([$recipe->id => ['batches' => 4]]);

        $plan = (new CalculateProductionPlan(new CalculateIngredientCosting))->handle($run->fresh());

        $row = collect($plan['rows'])->firstOrFail();
        expect((float) $row['required'])->toBe(600.0);
        expect($plan['total_units'])->toBe(60);
    });

    it('buys in real minimum package sizes -- deli cups (min 50) and a bottled ingredient (946g), not raw shortfalls', function () {
        $deliCups = Ingredient::create(['name' => 'Deli Cups', 'unit_type' => 'unit', 'waste_percent' => 100]);
        $deliCups->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1, 'total_price' => 0.17]);
        // Real minimum order size for this source -- the old ingredient-
        // level weight_per_unit fallback no longer exists, so the winning
        // provider (GFS) needs its own PackageSize row to be found at all.
        $deliCups->packageSizes()->create(['provider' => 'GFS', 'package_size' => 50]);

        $beefConcentrate = Ingredient::create(['name' => 'Beef Stock Concentrate', 'unit_type' => 'g', 'waste_percent' => 100]);
        $beefConcentrate->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 946, 'total_price' => 9.46]); // $10/kg
        $beefConcentrate->packageSizes()->create(['provider' => 'GFS', 'package_size' => 946]);

        $recipe = Recipe::create(['name' => 'Package Size Flavour', 'fill_size_g' => null]);
        $recipe->ingredients()->sync([
            $deliCups->id => ['quantity_per_jar' => 1],   // 20 jars -> needs 20 cups
            $beefConcentrate->id => ['quantity_per_jar' => 5], // 20 jars -> needs 100g
        ]);

        $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
        $run->recipes()->sync([$recipe->id => ['batches' => 20]]);

        $plan = (new CalculateProductionPlan(new CalculateIngredientCosting))->handle($run->fresh());
        $rows = collect($plan['rows'])->keyBy('ingredient_name');

        // 20 cups needed, minimum order is a case of 50 -- buy 1 case, not "20 units".
        expect((float) $rows['Deli Cups']['to_purchase'])->toBe(20.0);
        expect($rows['Deli Cups']['units_to_buy'])->toBe(1);
        expect((float) $rows['Deli Cups']['purchase_qty'])->toBe(50.0);
        expect((float) $rows['Deli Cups']['est_cost'])->toBe(8.5); // $0.17/unit x 50

        // 100g needed, sold only in fixed 946g bottles -- buy 1 bottle, not "100g".
        expect((float) $rows['Beef Stock Concentrate']['to_purchase'])->toBe(100.0);
        expect($rows['Beef Stock Concentrate']['units_to_buy'])->toBe(1);
        expect((float) $rows['Beef Stock Concentrate']['purchase_qty'])->toBe(946.0);
        expect((float) $rows['Beef Stock Concentrate']['est_cost'])->toBe(9.46); // $10/kg x 946g / 1000
    });

    it('buys enough to cover waste and prices the purchase at the shelf price', function () {
        // 90% usable: $10 for a 2000g bag of whole apples.
        $apples = Ingredient::create(['name' => 'Waste Plan Apples', 'unit_type' => 'g', 'waste_percent' => 90]);
        $apples->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'Store', 'qty' => 2000, 'total_price' => 10]);
        $apples->packageSizes()->create(['provider' => 'Store', 'package_size' => 2000]);

        $recipe = Recipe::create(['name' => 'Waste Plan Flavour', 'fill_size_g' => null]);
        $recipe->ingredients()->sync([$apples->id => ['quantity_per_jar' => 90]]);

        $run = ProductionRun::create(['batch_size' => 20, 'run_date' => Carbon::now()->toDateString()]);
        $run->recipes()->sync([$recipe->id => ['batches' => 2]]);

        $row = collect((new CalculateProductionPlan(new CalculateIngredientCosting))->handle($run->fresh())['rows'])->firstOrFail();

        // 40 units x 90g usable = 3600g usable = 4000g whole -> 2 bags, at $10 each.
        expect((float) $row['required'])->toBe(4000.0);
        expect($row['units_to_buy'])->toBe(2);
        expect((float) $row['est_cost'])->toBe(20.0);
    });

    it('counts only the usable share of stock towards units it can make', function () {
        $apples = Ingredient::create(['name' => 'Waste Producible Apples', 'unit_type' => 'g', 'waste_percent' => 90]);
        $apples->packageSizes()->create(['provider' => 'Store', 'package_size' => 1000, 'quantity_on_hand' => 1000]);

        // 1000g whole = 900g usable = 9 units at 100g, not 10.
        $recipe = Recipe::create(['name' => 'Waste Producible Recipe']);
        $recipe->mainIngredients()->sync([$apples->id => ['quantity_per_jar' => 100]]);

        expect((new CalculateMaxProducibleUnits)->handle($recipe->fresh()))->toBe(9);
    });

    it('plans the recipe amounts as-is per unit -- the fill weight only affects cost', function () {
        $base = Ingredient::create(['name' => 'Fill Plan Base', 'unit_type' => 'g', 'waste_percent' => 100]);
        $mixIn = Ingredient::create(['name' => 'Fill Plan Mix-in', 'unit_type' => 'g', 'waste_percent' => 100]);
        $cup = Ingredient::create(['name' => 'Fill Plan Cup', 'unit_type' => 'unit', 'waste_percent' => 100]);

        // 320g of ingredients per planned unit, filled at 280g: the extra
        // fills bonus units, so planning still needs the full 320g each.
        $recipe = Recipe::create(['name' => 'Fill Plan Flavour', 'fill_size_g' => 280]);
        $recipe->ingredients()->sync([
            $base->id => ['quantity_per_jar' => 250],
            $mixIn->id => ['quantity_per_jar' => 70],
            $cup->id => ['quantity_per_jar' => 1],
        ]);

        $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
        $run->recipes()->sync([$recipe->id => ['batches' => 8]]);

        $rows = collect((new CalculateProductionPlan(new CalculateIngredientCosting))->handle($run->fresh())['rows'])->keyBy('ingredient_name');

        expect((float) $rows['Fill Plan Base']['required'])->toBe(2000.0); // 250 x 8
        expect((float) $rows['Fill Plan Mix-in']['required'])->toBe(560.0); // 70 x 8
        expect((float) $rows['Fill Plan Cup']['required'])->toBe(8.0);
    });
});

describe('ProductionRun::totalUnits', function () {
    it('sums batch_size x batches across every recipe in the run', function () {
        $flavourA = Recipe::create(['name' => 'Total Units Flavour A']);
        $flavourB = Recipe::create(['name' => 'Total Units Flavour B']);

        $run = ProductionRun::create(['batch_size' => 20, 'run_date' => Carbon::now()->toDateString()]);
        $run->recipes()->sync([
            $flavourA->id => ['batches' => 3], // 60 units
            $flavourB->id => ['batches' => 2], // 40 units
        ]);

        expect($run->fresh()->totalUnits())->toBe(100);
    });
});

describe('CalculateRecipeCost', function () {
    /**
     * Reproduces the user's real "Dangerously Dilly" spreadsheet example
     * end-to-end, to confirm the ported formula ties out. Two deliberate
     * corrections from the sheet (both discussed with and approved by the
     * user): the 10% buffer is a configurable per-recipe field here rather
     * than hardcoded, and the yield/weight sum excludes the Deli Cup's "1"
     * unit-count (a container isn't part of the product's mass) -- so the
     * expected Food Cost % here is intentionally a hair off the sheet's
     * 31.81%, which had an inflated 313.5g denominator.
     */
    it('reproduces the Dangerously Dilly spreadsheet example, with the yield/buffer corrections', function () {
        $creamCheese = Ingredient::create(['name' => 'Cream Cheese', 'unit_type' => 'g', 'waste_percent' => 100]);
        $creamCheese->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 10.11]);

        $pickles = Ingredient::create(['name' => 'Pickles', 'unit_type' => 'g', 'waste_percent' => 100]);
        $pickles->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 5.83]);

        $driedDill = Ingredient::create(['name' => 'Dried Dill', 'unit_type' => 'g', 'waste_percent' => 100]);
        $driedDill->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 77.00]);

        // Logged as a real, known $0.00 price (not "no data") -- free, but
        // its weight still counts toward yield since it's a real gram-based
        // ingredient in the recipe.
        $pickleJuice = Ingredient::create(['name' => 'Pickle Juice', 'unit_type' => 'g', 'waste_percent' => 100]);
        $pickleJuice->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 0]);

        $deliCup = Ingredient::create(['name' => '8oz Deli Cup', 'unit_type' => 'unit', 'waste_percent' => 100]);
        $deliCup->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1, 'total_price' => 0.17]);

        $recipe = Recipe::create([
            'name' => 'Dangerously Dilly',
            'sell_price' => 10.00,
            'fill_size_g' => 285,
            'cost_buffer_percent' => 10,
        ]);
        $recipe->mainIngredients()->sync([
            $creamCheese->id => ['quantity_per_jar' => 250],
            $pickles->id => ['quantity_per_jar' => 50],
            $driedDill->id => ['quantity_per_jar' => 2.5],
            $pickleJuice->id => ['quantity_per_jar' => 10],
            $deliCup->id => ['quantity_per_jar' => 1],
        ]);

        $result = (new CalculateRecipeCost(new CalculateIngredientCosting))->handle($recipe->fresh());

        // Raw cost: 250*10.11/1000 + 50*5.83/1000 + 2.5*77/1000 + 10*0/1000 + 1*0.17 = 3.1815 -- matches the sheet's $3.18.
        expect(round($result['raw_cost'], 2))->toBe(3.18);
        // Yield: 250+50+2.5+10 = 312.5g -- the Deli Cup's "1" unit excluded (sheet's 313.5g included it, which is the bug being fixed).
        expect($result['yield_grams'])->toBe(312.5);
        // Buffered: 3.1815 * 1.10 = 3.49965 -- matches the sheet's $3.50.
        expect(round($result['buffered_cost'], 2))->toBe(3.50);
        // Actual cost/jar, prorated by real fill size: 3.49965/312.5*285 = 3.19169... -- close to but not identical to the sheet's $3.18 x (285/313.5), confirming the corrected (smaller, more accurate) yield denominator.
        expect(round($result['actual_cost_per_jar'], 2))->toBe(3.19);
        // Food cost %: 3.19169/10*100 = 31.92% -- close to but distinct from the sheet's 31.81%, as expected from the yield correction.
        expect($result['food_cost_percent'])->toBe(31.92);
        expect($result['any_stale'])->toBeFalse();
        expect($result['any_missing'])->toBeFalse();

        // ingredient_breakdown: one entry per main ingredient, correct
        // qty/price/cost/status per line -- this is what gets frozen into
        // a RecipeCostSnapshot's audit detail.
        $breakdown = collect($result['ingredient_breakdown'])->keyBy('name');
        expect($breakdown)->toHaveCount(5);

        expect($breakdown['Cream Cheese']['quantity_per_jar'])->toBe(250.0);
        expect($breakdown['Cream Cheese']['effective_price'])->toBe(10.11);
        expect(round($breakdown['Cream Cheese']['cost_contribution'], 4))->toBe(2.5275);
        expect($breakdown['Cream Cheese']['status'])->toBe('ok');

        expect($breakdown['Pickle Juice']['cost_contribution'])->toBe(0.0);
        expect($breakdown['Pickle Juice']['status'])->toBe('ok'); // a real, known $0.00 price -- not "missing"

        expect($breakdown['8oz Deli Cup']['unit_type'])->toBe('unit');
        expect($breakdown['8oz Deli Cup']['effective_price'])->toBe(0.17);
        expect($breakdown['8oz Deli Cup']['cost_contribution'])->toBe(0.17);
    });

    it('treats a missing cost_buffer_percent as 0% (no buffer)', function () {
        $ingredient = Ingredient::create(['name' => 'No Buffer Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
        $ingredient->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 10]);

        $recipe = Recipe::create(['name' => 'No Buffer Recipe']);
        $recipe->mainIngredients()->sync([$ingredient->id => ['quantity_per_jar' => 100]]);

        $result = (new CalculateRecipeCost(new CalculateIngredientCosting))->handle($recipe->fresh());

        // 100g * $10/kg / 1000 = $1.00, unbuffered.
        expect($result['raw_cost'])->toBe(1.0);
        expect($result['buffered_cost'])->toBe(1.0);
    });

    it('falls back to the buffered total cost per jar when no fill size is set, and leaves food_cost_percent null with no sell price', function () {
        $ingredient = Ingredient::create(['name' => 'No Fill Size Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
        $ingredient->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 10]);

        $recipe = Recipe::create(['name' => 'No Fill Size Recipe', 'fill_size_g' => null]); // fill cleared, no sell_price
        $recipe->mainIngredients()->sync([$ingredient->id => ['quantity_per_jar' => 100]]);

        $result = (new CalculateRecipeCost(new CalculateIngredientCosting))->handle($recipe->fresh());

        expect($result['actual_cost_per_jar'])->toBe($result['buffered_cost']);
        expect($result['food_cost_percent'])->toBeNull();
    });

    it('flags any_missing when an ingredient has no price, but still counts its weight toward yield', function () {
        $priced = Ingredient::create(['name' => 'Priced Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
        $priced->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 10]);

        $unpriced = Ingredient::create(['name' => 'Unpriced Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
        // No price history at all.

        $recipe = Recipe::create(['name' => 'Missing Price Recipe']);
        $recipe->mainIngredients()->sync([
            $priced->id => ['quantity_per_jar' => 100],
            $unpriced->id => ['quantity_per_jar' => 50],
        ]);

        $result = (new CalculateRecipeCost(new CalculateIngredientCosting))->handle($recipe->fresh());

        expect($result['any_missing'])->toBeTrue();
        // Only the priced ingredient contributes cost: 100g * $10/kg / 1000 = $1.00.
        expect($result['raw_cost'])->toBe(1.0);
        // Both ingredients' weights count toward yield regardless of price availability.
        expect($result['yield_grams'])->toBe(150.0);

        $breakdown = collect($result['ingredient_breakdown'])->keyBy('name');
        expect($breakdown['Unpriced Ingredient']['status'])->toBe('missing');
        expect($breakdown['Unpriced Ingredient']['effective_price'])->toBeNull();
        expect($breakdown['Unpriced Ingredient']['cost_contribution'])->toBe(0.0);
    });
});

describe('CalculateMaxProducibleUnits', function () {
    it('is bottlenecked by whichever main ingredient runs out first, not just the first one listed', function () {
        $plentiful = Ingredient::create(['name' => 'Plentiful', 'unit_type' => 'g', 'waste_percent' => 100]);
        $plentiful->packageSizes()->create(['provider' => 'GFS', 'package_size' => 1000, 'quantity_on_hand' => 10000]); // 100 jars' worth at 100g/jar

        $scarce = Ingredient::create(['name' => 'Scarce', 'unit_type' => 'g', 'waste_percent' => 100]);
        $scarce->packageSizes()->create(['provider' => 'GFS', 'package_size' => 1000, 'quantity_on_hand' => 2000]); // only 40 jars' worth at 50g/jar

        $recipe = Recipe::create(['name' => 'Bottleneck Recipe', 'fill_size_g' => null]);
        $recipe->mainIngredients()->sync([
            $plentiful->id => ['quantity_per_jar' => 100],
            $scarce->id => ['quantity_per_jar' => 50],
        ]);

        $result = (new CalculateMaxProducibleUnits)->handle($recipe->fresh());

        expect($result)->toBe(40);
    });

    it('floors a fractional result down to a whole jar', function () {
        $ingredient = Ingredient::create(['name' => 'Fractional', 'unit_type' => 'g', 'waste_percent' => 100]);
        $ingredient->packageSizes()->create(['provider' => 'GFS', 'package_size' => 1000, 'quantity_on_hand' => 105]);

        $recipe = Recipe::create(['name' => 'Fractional Recipe', 'fill_size_g' => null]);
        $recipe->mainIngredients()->sync([$ingredient->id => ['quantity_per_jar' => 10]]);

        // 105 / 10 = 10.5 -- can't produce a partial jar, so this floors to 10.
        expect((new CalculateMaxProducibleUnits)->handle($recipe->fresh()))->toBe(10);
    });

    it('ignores byproducts entirely, even a scarce one that would otherwise bottleneck', function () {
        $main = Ingredient::create(['name' => 'Main', 'unit_type' => 'g', 'waste_percent' => 100]);
        $main->packageSizes()->create(['provider' => 'GFS', 'package_size' => 1000, 'quantity_on_hand' => 5000]); // 50 jars' worth at 100g/jar

        $byproduct = Ingredient::create(['name' => 'Byproduct', 'unit_type' => 'g', 'waste_percent' => 100, 'byproduct_name' => 'Juice']);
        $byproduct->packageSizes()->create(['provider' => 'GFS', 'package_size' => 1000, 'quantity_on_hand' => 10]); // would bottleneck to 0 if counted

        $recipe = Recipe::create(['name' => 'Byproduct Recipe', 'fill_size_g' => null]);
        $recipe->mainIngredients()->sync([$main->id => ['quantity_per_jar' => 100]]);
        $recipe->byproductIngredients()->sync([$byproduct->id => ['quantity_per_jar' => 200]]);

        expect((new CalculateMaxProducibleUnits)->handle($recipe->fresh()))->toBe(50);
    });

    it('returns zero when a main ingredient is completely out of stock', function () {
        $ingredient = Ingredient::create(['name' => 'Out of Stock', 'unit_type' => 'g', 'waste_percent' => 100]);
        // No packageSizes created at all -- on_hand defaults to 0.

        $recipe = Recipe::create(['name' => 'Out of Stock Recipe', 'fill_size_g' => null]);
        $recipe->mainIngredients()->sync([$ingredient->id => ['quantity_per_jar' => 50]]);

        expect((new CalculateMaxProducibleUnits)->handle($recipe->fresh()))->toBe(0);
    });

    it('counts planned units at the recipe amounts, ignoring the fill weight', function () {
        $ingredient = Ingredient::create(['name' => 'Fill Producible', 'unit_type' => 'g', 'waste_percent' => 100]);
        $ingredient->packageSizes()->create(['provider' => 'GFS', 'package_size' => 1000, 'quantity_on_hand' => 1000]);

        // 400g per unit: 1000g makes 2 units, whatever the fill.
        $recipe = Recipe::create(['name' => 'Fill Producible Recipe', 'fill_size_g' => 200]);
        $recipe->mainIngredients()->sync([$ingredient->id => ['quantity_per_jar' => 400]]);

        expect((new CalculateMaxProducibleUnits)->handle($recipe->fresh()))->toBe(2);
    });

    it('returns zero for a recipe with no main ingredients at all', function () {
        $recipe = Recipe::create(['name' => 'Empty Recipe', 'fill_size_g' => null]);

        expect((new CalculateMaxProducibleUnits)->handle($recipe->fresh()))->toBe(0);
    });
});

describe('GenerateBatchCode', function () {
    it('generates PREFIX-YYMMDD-01 for the first run on a date, with no configured prefix', function () {
        $code = (new GenerateBatchCode)->handle(Carbon::parse('2026-09-10'));

        expect($code)->toBe('260910-01');
    });

    it('prefixes the code once a batch code prefix is configured', function () {
        (new UpdateSiteSetting)->handle(GetBatchCodePrefix::SETTING_KEY, 'CP');

        $code = (new GenerateBatchCode)->handle(Carbon::parse('2026-09-10'));

        expect($code)->toBe('CP-260910-01');
    });

    it('increments the sequence for additional runs on the same date, regardless of type', function () {
        ProductionRun::create(['type' => 'production', 'batch_size' => 20, 'run_date' => '2026-09-10']);
        ProductionRun::create(['type' => 'prep', 'batch_size' => 1, 'run_date' => '2026-09-10']);

        $code = (new GenerateBatchCode)->handle(Carbon::parse('2026-09-10'));

        expect($code)->toBe('260910-03');
    });

    it('keeps sequences independent across different dates', function () {
        ProductionRun::create(['type' => 'production', 'batch_size' => 20, 'run_date' => '2026-09-10']);

        $code = (new GenerateBatchCode)->handle(Carbon::parse('2026-09-11'));

        expect($code)->toBe('260911-01');
    });
});

describe('house-made ingredients', function () {
    // A bought ingredient at a fresh (yesterday) or stale (10 days ago) $/kg.
    $priced = function (string $name, float $perKg, int $daysAgo = 1): Ingredient {
        $ingredient = Ingredient::create(['name' => $name, 'unit_type' => 'g', 'waste_percent' => 100]);
        $ingredient->priceHistory()->create(['purchased_at' => Carbon::now()->subDays($daysAgo)->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => $perKg]);

        return $ingredient;
    };

    $houseMade = function (string $name, ?float $cookDownPercent, array $components): Ingredient {
        // Waste % deliberately not 100: it must be ignored for house-made.
        $ingredient = Ingredient::create(['name' => $name, 'unit_type' => 'g', 'waste_percent' => 50, 'is_house_made' => true, 'cook_down_percent' => $cookDownPercent]);
        $ingredient->components()->sync(collect($components)->mapWithKeys(fn ($qty, $id) => [$id => ['quantity' => $qty]])->all());

        return $ingredient;
    };

    it('costs a house-made ingredient from its components spread over the cooked yield, ignoring waste %', function () use ($priced, $houseMade) {
        $apples = $priced('HM Apples', 4);
        $sugar = $priced('HM Sugar', 2);
        // 800g x $4/kg + 200g x $2/kg = $3.60 per prep batch; 1000g in
        // cooks down to 40% = 400g out.
        $butter = $houseMade('HM Apple Butter', 40, [$apples->id => 800, $sugar->id => 200]);

        expect($butter->fresh()->yieldGrams())->toBe(400.0);

        $costing = (new CalculateIngredientCosting)->handle($butter->fresh());

        expect($costing['status'])->toBe('ok');
        expect($costing['effective_price'])->toBe(9.0);
        expect($costing['source_used'])->toBe('In-house');
    });

    it('scales the yield with the batch -- double the ingredients, double the yield, same cost per kg', function () use ($priced, $houseMade) {
        $apples = $priced('HM Scale Apples', 4);
        $small = $houseMade('HM Small Batch', 40, [$apples->id => 1000]);
        $large = $houseMade('HM Large Batch', 40, [$apples->id => 2000]);

        expect($small->fresh()->yieldGrams())->toBe(400.0);
        expect($large->fresh()->yieldGrams())->toBe(800.0);
        expect((new CalculateIngredientCosting)->handle($large->fresh())['effective_price'])
            ->toBe((new CalculateIngredientCosting)->handle($small->fresh())['effective_price']);
    });

    it('costs nested house-made ingredients recursively', function () use ($priced, $houseMade) {
        $juice = $priced('HM Juice', 2);
        $apples = $priced('HM Nested Apples', 4);
        $cider = $houseMade('HM Boiled Cider', 20, [$juice->id => 1000]); // $2 over 200g = $10/kg
        // 100g cider ($1.00) + 900g apples ($3.60) = $4.60 over 50% of 1000g.
        $butter = $houseMade('HM Nested Butter', 50, [$cider->id => 100, $apples->id => 900]);

        expect((new CalculateIngredientCosting)->handle($butter->fresh())['effective_price'])->toBe(9.2);
    });

    it('falls back to a flagged estimate when a component price is stale, and is unpriced without a cook-down %', function () use ($priced, $houseMade) {
        $old = $priced('HM Old Apples', 4, daysAgo: 10);
        $stale = $houseMade('HM Stale Butter', 40, [$old->id => 1000]);
        $noYield = $houseMade('HM No Yield', null, [$old->id => 1000]);

        $staleCosting = (new CalculateIngredientCosting)->handle($stale->fresh());
        expect($staleCosting['status'])->toBe('no_price_this_week');
        expect($staleCosting['effective_price'])->toBeNull();
        expect($staleCosting['stale_effective_price'])->toBe(10.0);

        $unpriced = (new CalculateIngredientCosting)->handle($noYield->fresh());
        expect($unpriced['effective_price'])->toBeNull();
        expect($unpriced['stale_effective_price'])->toBeNull();
    });

    it('flows into recipe cost like any other ingredient', function () use ($priced, $houseMade) {
        $apples = $priced('HM Recipe Apples', 4);
        $butter = $houseMade('HM Recipe Butter', 40, [$apples->id => 1000]); // $4 over 400g = $10/kg

        $recipe = Recipe::create(['name' => 'HM Recipe', 'fill_size_g' => null]);
        $recipe->mainIngredients()->sync([$butter->id => ['quantity_per_jar' => 50]]);

        expect((new CalculateRecipeCost(new CalculateIngredientCosting))->handle($recipe->fresh())['raw_cost'])->toBe(0.5);
    });

    it('preps house-made ingredients instead of buying them, adding their components to the shopping list', function () use ($priced, $houseMade) {
        $cheese = $priced('HM Plan Cheese', 10);
        $apples = $priced('HM Plan Apples', 4);
        $sugar = $priced('HM Plan Sugar', 2);
        $butter = $houseMade('HM Plan Butter', 40, [$apples->id => 800, $sugar->id => 200]); // 400g per prep batch

        $recipe = Recipe::create(['name' => 'HM Plan Flavour', 'fill_size_g' => null]);
        $recipe->ingredients()->sync([
            $cheese->id => ['quantity_per_jar' => 250],
            $butter->id => ['quantity_per_jar' => 50],
        ]);

        $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
        $run->recipes()->sync([$recipe->id => ['batches' => 10]]);

        $plan = (new CalculateProductionPlan(new CalculateIngredientCosting))->handle($run->fresh());
        $rows = collect($plan['rows'])->keyBy('ingredient_name');

        // 10 units x 50g = 500g of butter = 1.25 prep batches of 400g.
        expect($plan['prep_rows'])->toHaveCount(1);
        expect((float) $plan['prep_rows'][0]['required'])->toBe(500.0);
        expect((float) $plan['prep_rows'][0]['prep_batches'])->toBe(1.25);

        expect($rows->has('HM Plan Butter'))->toBeFalse();
        expect((float) $rows['HM Plan Cheese']['required'])->toBe(2500.0);
        expect((float) $rows['HM Plan Apples']['required'])->toBe(1000.0);
        expect((float) $rows['HM Plan Sugar']['required'])->toBe(250.0);
    });
});

describe('seed data', function () {
    it('seeds Autumn Apple Cinnamon with its house-made Apple Butter, priced and ready to plan', function () {
        $this->seed(\Cultpantry\Costing\Database\Seeders\CostingDatabaseSeeder::class);

        $butter = Ingredient::where('name', 'Apple Butter')->firstOrFail();
        expect($butter->is_house_made)->toBeTrue();
        expect(round($butter->yieldGrams()))->toBe(900.0);
        expect($butter->components)->toHaveCount(10);
        expect((float) $butter->components->firstWhere('name', 'Apples Pink Ladies')->pivot->quantity)->toBe(2100.0);

        // Every component freshly priced, so the butter is too: $12.87 per
        // 900g prep batch = $14.30/kg.
        $costing = (new CalculateIngredientCosting)->handle($butter->fresh());
        expect($costing['status'])->toBe('ok');
        expect(round($costing['effective_price'], 2))->toBe(14.3);

        $recipe = Recipe::where('name', 'Autumn Apple Cinnamon')->firstOrFail();
        expect($recipe->preferred_batch_size)->toBe(20);
        expect((float) $recipe->sell_price)->toBe(10.0);
        expect($recipe->ingredients->pluck('pivot.quantity_per_jar', 'name')->map(fn ($q) => (float) $q)->sortKeys()->all())->toBe([
            '8oz Deli Cup' => 1.0,
            '8oz Deli Lid' => 1.0,
            'Apple Butter' => 45.0,
            'Apples Pink Ladies' => 40.0,
            'Cream Cheese' => 250.0,
        ]);

        expect(round((new CalculateRecipeCost(new CalculateIngredientCosting))->handle($recipe->fresh())['actual_cost_per_jar'], 2))->toBe(3.55);
    });
});
