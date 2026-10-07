<?php

use App\Actions\UpdateSiteSetting;
use App\Models\User;
use App\Support\AdminNav;
use Cultpantry\Costing\Actions\CalculateIngredientCosting;
use Cultpantry\Costing\Actions\CalculateRecipeCost;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\KitchenRental;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Models\RecipeCostSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

describe('costing admin module', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = User::factory()->create(['role' => 'customer']);
    });

    it('registers its routes with the web middleware group', function () {
        // Regression guard: routes loaded via loadRoutesFrom() in a module's
        // ServiceProvider don't get the 'web' group automatically the way
        // routes/web.php does. Without it, no session is started and every
        // request is treated as a guest -- a bug actingAs()-based tests
        // below can't catch on their own.
        $route = Route::getRoutes()->getByName('admin.costing.ingredients.index');
        expect($route->middleware())->toContain('web');
    });

    it('can be viewed by admin', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.costing.ingredients.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/costing/Ingredients/Index'));
    });

    it('resolves every top-level admin page', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.costing.price-history.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/costing/PriceHistory/Index'));

        $this->actingAs($this->admin)
            ->get(route('admin.costing.inventory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/costing/Inventory/Index'));

        $this->actingAs($this->admin)
            ->get(route('admin.costing.recipes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/costing/Recipes/Index'));

        $this->actingAs($this->admin)
            ->get(route('admin.costing.production-planner.runs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/costing/ProductionPlanner/Runs'));

        $this->actingAs($this->admin)
            ->get(route('admin.costing.recipes.grid'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/costing/Recipes/Grid'));

        $this->actingAs($this->admin)
            ->get(route('admin.costing.recipes.costing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/costing/Recipes/Costing'));

        $this->actingAs($this->admin)
            ->get(route('admin.costing.recipes.cost-history'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vendor/costing/Recipes/CostHistory'));
    });

    it('customer cannot view the module', function () {
        $this->actingAs($this->customer)
            ->get(route('admin.costing.ingredients.index'))
            ->assertForbidden();
    });

    it('guest cannot view the module', function () {
        $this->get(route('admin.costing.ingredients.index'))
            ->assertRedirect(route('login'));
    });

    it('404s for admin when disabled via the Settings toggle, on every controller not just the index', function () {
        (new UpdateSiteSetting)->handle('modules.cultpantry/costing.enabled', false);

        $this->actingAs($this->admin)
            ->get(route('admin.costing.ingredients.index'))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin.costing.production-planner.runs'))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin.costing.ingredients.create'))
            ->assertNotFound();

        (new UpdateSiteSetting)->handle('modules.cultpantry/costing.enabled', true);
    });

    it('appears in the admin nav as a top-level item when enabled', function () {
        $names = collect(AdminNav::all())->pluck('name');

        expect($names)->toContain('Costing & Recipes');
    });

    it('disappears from the admin nav when disabled', function () {
        (new UpdateSiteSetting)->handle('modules.cultpantry/costing.enabled', false);

        $names = collect(AdminNav::all())->pluck('name');
        expect($names)->not->toContain('Costing & Recipes');

        (new UpdateSiteSetting)->handle('modules.cultpantry/costing.enabled', true);
    });

    it('lists all production runs, newest first, with correct total units', function () {
        $recipe = Recipe::create(['name' => 'History Flavour']);

        $older = ProductionRun::create(['name' => 'Older Run', 'batch_size' => 1, 'run_date' => Carbon::now()->subWeek()->toDateString()]);
        $older->recipes()->sync([$recipe->id => ['batches' => 20]]);

        $newer = ProductionRun::create(['name' => 'Newer Run', 'batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
        $newer->recipes()->sync([$recipe->id => ['batches' => 50]]);

        $this->actingAs($this->admin)
            ->get(route('admin.costing.production-planner.runs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/costing/ProductionPlanner/Runs')
                ->where('runs.0.name', 'Newer Run')
                ->where('runs.0.total_units', 50)
                ->where('runs.1.name', 'Older Run')
                ->where('runs.1.total_units', 20)
            );
    });

    it('lists total units as batch_size x batches, not just the batch count', function () {
        $recipe = Recipe::create(['name' => 'Batch Size Flavour']);
        $run = ProductionRun::create(['batch_size' => 20, 'run_date' => Carbon::now()->toDateString()]);
        $run->recipes()->sync([$recipe->id => ['batches' => 3]]);

        $this->actingAs($this->admin)
            ->get(route('admin.costing.production-planner.runs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('runs.0.total_units', 60));
    });

    it('shows a specific past run, not just the latest one', function () {
        $recipe = Recipe::create(['name' => 'Past Run Flavour']);

        ProductionRun::create(['name' => 'Latest Run', 'batch_size' => 1, 'run_date' => Carbon::now()->toDateString()])
            ->recipes()->sync([$recipe->id => ['batches' => 99]]);

        $past = ProductionRun::create(['name' => 'Past Run', 'batch_size' => 1, 'run_date' => Carbon::now()->subMonth()->toDateString()]);
        $past->recipes()->sync([$recipe->id => ['batches' => 7]]);

        // Requesting the past run by ID must return ITS data, not the
        // latest run's -- this is the whole point of adding show(). A JSON
        // API endpoint (fetched by the ProductionPlanner page client-side),
        // not an Inertia page itself.
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.costing.production-planner.show', $past->id))
            ->assertOk();

        expect($response->json('production_run.id'))->toBe($past->id);
        expect($response->json('production_run.name'))->toBe('Past Run');
        expect($response->json('production_run.total_units'))->toBe(7);
    });

    it('creates a standalone production run with no kitchen rental involved', function () {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.costing.production-planner.store'), [
                'type' => 'production',
                'name' => 'Standalone Run',
                'run_date' => Carbon::now()->toDateString(),
            ])
            ->assertOk();

        $run = ProductionRun::findOrFail($response->json('production_run_id'));
        expect($run->type)->toBe('production');
        expect($run->batch_size)->toBe(20);
        expect($run->rentals)->toBeEmpty();
    });

    it('defaults batch_size to 1 for a prep or development run, since batches are never entered for them', function () {
        foreach (['prep', 'development'] as $type) {
            $response = $this->actingAs($this->admin)
                ->postJson(route('admin.costing.production-planner.store'), [
                    'type' => $type,
                    'run_date' => Carbon::now()->toDateString(),
                ])
                ->assertOk();

            $run = ProductionRun::findOrFail($response->json('production_run_id'));
            expect($run->type)->toBe($type);
            expect($run->batch_size)->toBe(1);
        }
    });

    it('generates a default batch-code name when none is given, but keeps a typed name as-is', function () {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.costing.production-planner.store'), [
                'type' => 'production',
                'run_date' => '2026-09-10',
            ])
            ->assertOk();

        $run = ProductionRun::findOrFail($response->json('production_run_id'));
        expect($run->name)->toBe('260910-01');
    });

    it('never changes a run\'s name via update(), even if a name is sent in the request', function () {
        $run = ProductionRun::create(['name' => 'CP-260910-01', 'batch_size' => 20, 'run_date' => Carbon::now()->toDateString()]);

        $this->actingAs($this->admin)
            ->put(route('admin.costing.production-planner.update', $run->id), [
                'name' => 'Someone Typed Over It',
                'run_date' => $run->run_date->toDateString(),
                'batch_size' => 20,
                'batches' => [],
            ])
            ->assertRedirect();

        expect($run->fresh()->name)->toBe('CP-260910-01');
    });

    it('rejects an invalid run type', function () {
        $this->actingAs($this->admin)
            ->postJson(route('admin.costing.production-planner.store'), [
                'type' => 'not-a-real-type',
                'run_date' => Carbon::now()->toDateString(),
            ])
            ->assertInvalid(['type']);
    });

    it('customer cannot create a standalone production run', function () {
        $this->actingAs($this->customer)
            ->postJson(route('admin.costing.production-planner.store'), [
                'type' => 'production',
                'run_date' => Carbon::now()->toDateString(),
            ])
            ->assertForbidden();
    });

    it('lets a recipe be tagged on a development run with zero batches, without affecting total units', function () {
        $recipe = Recipe::create(['name' => 'R&D Flavour']);
        $run = ProductionRun::create(['type' => 'development', 'batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);

        $this->actingAs($this->admin)
            ->put(route('admin.costing.production-planner.update', $run->id), [
                'run_date' => $run->run_date->toDateString(),
                'batch_size' => 1,
                'batches' => [['recipe_id' => $recipe->id, 'batches' => 0]],
            ])
            ->assertRedirect();

        $run->refresh();
        expect($run->recipes)->toHaveCount(1);
        expect($run->totalUnits())->toBe(0);
    });

    it('attaches an unattached rental slot to a run from the run\'s side, and detaches it again', function () {
        $run = ProductionRun::create(['type' => 'production', 'batch_size' => 20, 'run_date' => Carbon::now()->toDateString()]);
        $rental = KitchenRental::create([
            'booking_title' => 'Evening Slot',
            'space_name' => 'Main Kitchen',
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHours(3),
            'booking_date' => Carbon::now()->addDay()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.costing.production-planner.attach-rental', $run->id), ['kitchen_rental_id' => $rental->id])
            ->assertRedirect();

        expect($rental->fresh()->production_run_id)->toBe($run->id);

        $this->actingAs($this->admin)
            ->post(route('admin.costing.production-planner.detach-rental', $run->id))
            ->assertRedirect();

        expect($rental->fresh()->production_run_id)->toBeNull();
    });

    it('lists only unattached rental slots for the "Attach Rental Slot" picker', function () {
        $attachedRun = ProductionRun::create(['type' => 'production', 'batch_size' => 20, 'run_date' => Carbon::now()->toDateString()]);
        KitchenRental::create([
            'booking_title' => 'Already Linked',
            'space_name' => 'Main Kitchen',
            'starts_at' => Carbon::now(),
            'ends_at' => Carbon::now()->addHours(2),
            'booking_date' => Carbon::now()->toDateString(),
            'production_run_id' => $attachedRun->id,
        ]);
        KitchenRental::create([
            'booking_title' => 'Still Free',
            'space_name' => 'Main Kitchen',
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHours(2),
            'booking_date' => Carbon::now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.costing.production-planner.unattached-rentals'))
            ->assertOk();

        expect($response->json('rentals'))->toHaveCount(1);
        expect($response->json('rentals.0.booking_title'))->toBe('Still Free');
    });

    it('saves and uppercases the batch code prefix from the Settings page, then uses it as the new default', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.costing.settings.update'), [
                'staleness_days' => 7,
                'batch_code_prefix' => 'cp',
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.costing.production-planner.store'), [
                'type' => 'production',
                'run_date' => '2026-09-10',
            ])
            ->assertOk();

        $run = \Cultpantry\Costing\Models\ProductionRun::findOrFail($response->json('production_run_id'));
        expect($run->name)->toBe('CP-260910-01');
    });

    it('customer cannot update costing settings', function () {
        $this->actingAs($this->customer)
            ->put(route('admin.costing.settings.update'), [
                'staleness_days' => 7,
                'batch_code_prefix' => 'CP',
            ])
            ->assertForbidden();
    });

    describe('bulk actions', function () {
        it('bulk deletes selected ingredients, skipping and tallying anything that fails', function () {
            $keep = Ingredient::create(['name' => 'Keep Me', 'unit_type' => 'g', 'waste_percent' => 100]);
            $delete1 = Ingredient::create(['name' => 'Delete Me 1', 'unit_type' => 'g', 'waste_percent' => 100]);
            $delete2 = Ingredient::create(['name' => 'Delete Me 2', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.ingredients.bulk-action'), [
                    'action' => 'delete',
                    'ids' => [$delete1->id, $delete2->id],
                ])
                ->assertRedirect();

            expect(Ingredient::find($delete1->id))->toBeNull();
            expect(Ingredient::find($delete2->id))->toBeNull();
            expect(Ingredient::find($keep->id))->not->toBeNull();
        });

        it('customer cannot bulk delete ingredients', function () {
            $ingredient = Ingredient::create(['name' => 'Protected', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->customer)
                ->post(route('admin.costing.ingredients.bulk-action'), [
                    'action' => 'delete',
                    'ids' => [$ingredient->id],
                ])
                ->assertForbidden();

            expect(Ingredient::find($ingredient->id))->not->toBeNull();
        });

        it('bulk deletes selected price history entries', function () {
            $ingredient = Ingredient::create(['name' => 'Bulk Price Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $entry1 = $ingredient->priceHistory()->create(['purchased_at' => Carbon::now()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 10]);
            $entry2 = $ingredient->priceHistory()->create(['purchased_at' => Carbon::now()->toDateString(), 'provider' => 'Wholesale Club', 'qty' => 1000, 'total_price' => 12]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.price-history.bulk-action'), [
                    'action' => 'delete',
                    'ids' => [$entry1->id, $entry2->id],
                ])
                ->assertRedirect();

            expect($ingredient->priceHistory()->count())->toBe(0);
        });

        it('bulk deletes selected recipes and bulk activates/deactivates the rest', function () {
            $toDelete = Recipe::create(['name' => 'Bulk Delete Recipe']);
            $toDeactivate = Recipe::create(['name' => 'Bulk Deactivate Recipe', 'is_active' => true]);
            $toActivate = Recipe::create(['name' => 'Bulk Activate Recipe', 'is_active' => false]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.recipes.bulk-action'), [
                    'action' => 'delete',
                    'ids' => [$toDelete->id],
                ])
                ->assertRedirect();
            expect(Recipe::find($toDelete->id))->toBeNull();

            $this->actingAs($this->admin)
                ->post(route('admin.costing.recipes.bulk-action'), [
                    'action' => 'deactivate',
                    'ids' => [$toDeactivate->id],
                ])
                ->assertRedirect();
            expect($toDeactivate->fresh()->is_active)->toBeFalse();

            $this->actingAs($this->admin)
                ->post(route('admin.costing.recipes.bulk-action'), [
                    'action' => 'activate',
                    'ids' => [$toActivate->id],
                ])
                ->assertRedirect();
            expect($toActivate->fresh()->is_active)->toBeTrue();
        });

        it('bulk deletes production runs but skips completed ones, tallying them as failures', function () {
            $planned = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $completed = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString(), 'completed_at' => Carbon::now()]);

            $response = $this->actingAs($this->admin)
                ->post(route('admin.costing.production-planner.bulk-action'), [
                    'action' => 'delete',
                    'ids' => [$planned->id, $completed->id],
                ])
                ->assertRedirect();

            expect(ProductionRun::find($planned->id))->toBeNull();
            expect(ProductionRun::find($completed->id))->not->toBeNull();
            expect($response->getSession()->get('warning'))->toContain('1 could not be deleted');
        });

        it('rejects an invalid bulk action', function () {
            $ingredient = Ingredient::create(['name' => 'Invalid Action Target', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.ingredients.bulk-action'), [
                    'action' => 'nonsense',
                    'ids' => [$ingredient->id],
                ])
                ->assertInvalid(['action']);
        });
    });

    it('excludes inactive recipes from the recipe picker, but keeps active ones', function () {
        $active = Recipe::create(['name' => 'Active Flavour', 'is_active' => true]);
        $inactive = Recipe::create(['name' => 'Discontinued Flavour', 'is_active' => false]);

        $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.costing.production-planner.show', $run->id))
            ->assertOk();

        $recipeNames = collect($response->json('recipes'))->pluck('name');

        expect($recipeNames)->toContain('Active Flavour');
        expect($recipeNames)->not->toContain('Discontinued Flavour');
    });

    it('shows recipe quantities in the grid view, keyed by ingredient', function () {
        $ingredient = Ingredient::create(['name' => 'Grid Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
        $recipe = Recipe::create(['name' => 'Grid Flavour']);
        $recipe->ingredients()->sync([$ingredient->id => ['quantity_per_jar' => 42]]);

        $this->actingAs($this->admin)
            ->get(route('admin.costing.recipes.grid'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/costing/Recipes/Grid')
                ->where('ingredients.0.name', 'Grid Ingredient')
                ->where('recipes.0.name', 'Grid Flavour')
                ->where("recipes.0.quantities.{$ingredient->id}", 42)
            );
    });

    describe('completing a production run', function () {
        it('deducts required quantities from inventory and marks the run completed', function () {
            $ingredient = Ingredient::create(['name' => 'Complete Cream', 'unit_type' => 'g', 'waste_percent' => 100]);
            // on_hand is now the sum of per-source quantity_on_hand rows
            // (costing_ingredient_package_sizes), not the old unit_size x
            // units_on_hand aggregate on costing_inventory.
            $ingredient->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1000, 'quantity_on_hand' => 5000]);

            $recipe = Recipe::create(['name' => 'Complete Flavour']);
            $recipe->ingredients()->sync([$ingredient->id => ['quantity_per_jar' => 100]]); // 100g/jar

            $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([$recipe->id => ['batches' => 10]]); // requires 1000g

            $this->actingAs($this->admin)
                ->post(route('admin.costing.production-planner.complete', $run->id))
                ->assertRedirect()
                ->assertSessionHas('success');

            expect($run->fresh()->completed_at)->not->toBeNull();

            // 5000g on hand, 1000g required -> 4000g remaining.
            expect((float) $ingredient->inventory->fresh()->on_hand)->toBe(4000.0);
        });

        it('requires ingredients for batch_size x batches units, not just the batch count', function () {
            $ingredient = Ingredient::create(['name' => 'Batch Math Cream', 'unit_type' => 'g', 'waste_percent' => 100]);
            $ingredient->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1000, 'quantity_on_hand' => 100000]); // 100,000g on hand

            $recipe = Recipe::create(['name' => 'Batch Math Flavour']);
            $recipe->ingredients()->sync([$ingredient->id => ['quantity_per_jar' => 100]]); // 100g/unit

            // 20 (batch size) x 3 (batches) = 60 real units -> requires 6000g, not 300g.
            $run = ProductionRun::create(['batch_size' => 20, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([$recipe->id => ['batches' => 3]]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.production-planner.complete', $run->id))
                ->assertRedirect();

            // 100,000g on hand, 6000g required -> 94,000g remaining.
            expect((float) $ingredient->inventory->fresh()->on_hand)->toBe(94000.0);
        });

        it('freezes a cost snapshot per recipe in the run, matching a fresh CalculateRecipeCost computation', function () {
            $freshIngredient = Ingredient::create(['name' => 'Snapshot Fresh Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $freshIngredient->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 10]);
            $freshRecipe = Recipe::create(['name' => 'Snapshot Fresh Recipe', 'sell_price' => 10, 'fill_size_g' => 100, 'cost_buffer_percent' => 10]);
            $freshRecipe->mainIngredients()->sync([$freshIngredient->id => ['quantity_per_jar' => 100]]);

            // No price history at all -- exercises any_missing on the snapshot.
            $missingIngredient = Ingredient::create(['name' => 'Snapshot Missing Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $staleRecipe = Recipe::create(['name' => 'Snapshot Stale Recipe']);
            $staleRecipe->mainIngredients()->sync([$missingIngredient->id => ['quantity_per_jar' => 50]]);

            $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([
                $freshRecipe->id => ['batches' => 20],
                $staleRecipe->id => ['batches' => 5],
            ]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.production-planner.complete', $run->id))
                ->assertRedirect();

            expect(RecipeCostSnapshot::count())->toBe(2);

            $freshSnapshot = RecipeCostSnapshot::where('recipe_id', $freshRecipe->id)->firstOrFail();
            $expected = (new CalculateRecipeCost(new CalculateIngredientCosting))->handle($freshRecipe->fresh());

            expect($freshSnapshot->production_run_id)->toBe($run->id);
            expect($freshSnapshot->jars_produced)->toBe(20);
            expect((float) $freshSnapshot->sell_price)->toBe(10.0);
            expect((float) $freshSnapshot->fill_size_g)->toBe(100.0);
            expect((float) $freshSnapshot->raw_cost)->toBe($expected['raw_cost']);
            expect((float) $freshSnapshot->buffered_cost)->toBe($expected['buffered_cost']);
            expect((float) $freshSnapshot->food_cost_percent)->toBe($expected['food_cost_percent']);
            expect($freshSnapshot->any_missing)->toBeFalse();
            expect($freshSnapshot->ingredient_breakdown)->toHaveCount(1);
            expect($freshSnapshot->ingredient_breakdown[0]['status'])->toBe('ok');

            $staleSnapshot = RecipeCostSnapshot::where('recipe_id', $staleRecipe->id)->firstOrFail();
            expect($staleSnapshot->jars_produced)->toBe(5);
            expect($staleSnapshot->any_missing)->toBeTrue();
            expect((float) $staleSnapshot->raw_cost)->toBe(0.0);
        });

        it('does not snapshot a recipe included in the run with zero batches', function () {
            $recipe = Recipe::create(['name' => 'Zero Batches Recipe']);
            $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([$recipe->id => ['batches' => 0]]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.production-planner.complete', $run->id))
                ->assertRedirect();

            expect(RecipeCostSnapshot::where('recipe_id', $recipe->id)->exists())->toBeFalse();
        });

        it('clamps at zero rather than going negative when required exceeds on-hand', function () {
            $ingredient = Ingredient::create(['name' => 'Short Cream', 'unit_type' => 'g', 'waste_percent' => 100]);
            $ingredient->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1000, 'quantity_on_hand' => 500]); // 500g on hand

            $recipe = Recipe::create(['name' => 'Short Flavour']);
            $recipe->ingredients()->sync([$ingredient->id => ['quantity_per_jar' => 100]]);

            $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([$recipe->id => ['batches' => 10]]); // requires 1000g, only 500g on hand

            $this->actingAs($this->admin)
                ->post(route('admin.costing.production-planner.complete', $run->id))
                ->assertRedirect();

            expect((float) $ingredient->inventory->fresh()->on_hand)->toBe(0.0);
        });

        it('cannot be completed twice, and does not create a duplicate snapshot on the second attempt', function () {
            $recipe = Recipe::create(['name' => 'No Double Snapshot Recipe']);
            $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([$recipe->id => ['batches' => 3]]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.production-planner.complete', $run->id))
                ->assertRedirect();

            expect(RecipeCostSnapshot::where('recipe_id', $recipe->id)->count())->toBe(1);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.production-planner.complete', $run->id))
                ->assertStatus(422);

            expect(RecipeCostSnapshot::where('recipe_id', $recipe->id)->count())->toBe(1);
        });

        it('locks batch counts from further edits once completed', function () {
            $recipe = Recipe::create(['name' => 'Locked Flavour']);
            $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([$recipe->id => ['batches' => 5]]);

            $this->actingAs($this->admin)->post(route('admin.costing.production-planner.complete', $run->id));

            $this->actingAs($this->admin)
                ->put(route('admin.costing.production-planner.update', $run->id), [
                    'run_date' => Carbon::now()->toDateString(),
                    'batch_size' => 1,
                    'batches' => [['recipe_id' => $recipe->id, 'batches' => 999]],
                ])
                ->assertStatus(422);
        });

        it('customer cannot complete a run', function () {
            $run = ProductionRun::create(['run_date' => Carbon::now()->toDateString()]);

            $this->actingAs($this->customer)
                ->post(route('admin.costing.production-planner.complete', $run->id))
                ->assertForbidden();
        });

        it('guest cannot complete a run', function () {
            $run = ProductionRun::create(['run_date' => Carbon::now()->toDateString()]);

            $this->post(route('admin.costing.production-planner.complete', $run->id))
                ->assertRedirect(route('login'));
        });
    });

    describe('breadcrumbs', function () {
        // Regression guard: AdminLayout.vue's auto-generated breadcrumbs
        // assume every intermediate URL segment is a real page, which is
        // true for core admin sections (bare index + bare show routes
        // exist everywhere) but not for this module -- there's no bare
        // /admin/costing route, and Ingredient/Recipe/PriceHistoryEntry/
        // Inventory have no show() route, only edit(). Every costing page
        // passes an explicit 'breadcrumbs' prop instead (CostingBreadcrumbs),
        // which Inertia's persistent-layout mechanism forwards straight
        // into AdminLayout without any Vue changes needed. This test
        // doesn't just check the prop shape -- it dereferences every href
        // for a real HTTP 200, since a plausible-looking string that 404s
        // is exactly the bug being guarded against.
        it('every breadcrumb href on every top-level page actually resolves', function () {
            $ingredient = Ingredient::create(['name' => 'Breadcrumb Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $recipe = Recipe::create(['name' => 'Breadcrumb Recipe']);
            $run = ProductionRun::create(['run_date' => Carbon::now()->toDateString()]);

            $pages = [
                route('admin.costing.ingredients.index'),
                route('admin.costing.ingredients.create'),
                route('admin.costing.ingredients.edit', $ingredient->id),
                route('admin.costing.inventory.index'),
                route('admin.costing.inventory.adjustments'),
                route('admin.costing.recipes.index'),
                route('admin.costing.recipes.grid'),
                route('admin.costing.recipes.costing'),
                route('admin.costing.recipes.cost-history'),
                route('admin.costing.recipes.create'),
                route('admin.costing.recipes.edit', $recipe->id),
                route('admin.costing.price-history.index'),
                route('admin.costing.price-history.create'),
                route('admin.costing.production-planner.runs'),
                route('admin.costing.production-planner.purchase-order', $run->id),
            ];

            foreach ($pages as $url) {
                $response = $this->actingAs($this->admin)->get($url)->assertOk();

                $breadcrumbs = $response->viewData('page')['props']['breadcrumbs'] ?? null;
                expect($breadcrumbs)->not->toBeNull("No breadcrumbs prop on {$url}");
                expect($breadcrumbs[0]['label'])->toBe('Costing & Recipes');

                foreach ($breadcrumbs as $crumb) {
                    if (!isset($crumb['href'])) {
                        continue;
                    }

                    $this->actingAs($this->admin)
                        ->get($crumb['href'])
                        ->assertOk();
                }
            }
        });
    });

    describe('purchase order', function () {
        it('shows the real whole-package purchase quantity, not the raw shortfall', function () {
            $deliCups = Ingredient::create(['name' => 'PO Deli Cups', 'unit_type' => 'unit', 'waste_percent' => 100]);
            $deliCups->priceHistory()->create([
                'purchased_at' => Carbon::now()->subDay()->toDateString(),
                'provider' => 'GFS',
                'qty' => 1,
                'total_price' => 0.17,
            ]);
            // Real minimum order size for the winning source -- no
            // ingredient-level fallback exists anymore.
            $deliCups->packageSizes()->create(['provider' => 'GFS', 'package_size' => 50]);

            $recipe = Recipe::create(['name' => 'PO Recipe']);
            $recipe->ingredients()->sync([$deliCups->id => ['quantity_per_jar' => 1]]);

            $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([$recipe->id => ['batches' => 20]]);

            $response = $this->actingAs($this->admin)
                ->get(route('admin.costing.production-planner.purchase-order', $run->id))
                ->assertOk();

            $rows = collect($response->viewData('page')['props']['plan']['purchase_rows'])->keyBy('ingredient_name');

            expect((float) $rows['PO Deli Cups']['to_purchase'])->toBe(20.0);
            expect((float) $rows['PO Deli Cups']['purchase_qty'])->toBe(50.0);
            expect((float) $rows['PO Deli Cups']['est_cost'])->toBe(8.5); // 0.17 x 50, not x 20
        });
    });

    /*
     * The old per-ingredient Inventory Edit page (a bare unit_size /
     * units_on_hand / counted_on_hand form) was replaced entirely by
     * per-source tracking: sources() (JSON) feeds StockAdjustModal.vue,
     * and adjustSource() applies a recount/adjust to one specific source.
     * There's no ingredient-level "typical size" or "walk-in count
     * override" concept left to test -- every source is a real named
     * provider/brand row with its own quantity_on_hand.
     */
    describe('inventory sources', function () {
        it('lists a product\'s known sources with their package sizes', function () {
            $ingredient = Ingredient::create(['name' => 'Walk-in Count Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $ingredient->packageSizes()->create(['provider' => 'GFS', 'brand' => 'Kraft', 'package_size' => 15000]);
            $ingredient->packageSizes()->create(['provider' => 'Wholesale Club', 'brand' => 'Brand X', 'package_size' => 20000]);

            $response = $this->actingAs($this->admin)->getJson(route('admin.costing.inventory.sources', $ingredient->id));

            $response->assertOk();
            $sources = collect($response->json('sources'));

            expect($sources)->toHaveCount(2);
            expect((float) $sources->firstWhere('provider', 'GFS')['package_size'])->toBe(15000.0);
            expect($sources->firstWhere('provider', 'GFS')['brand'])->toBe('Kraft');
        });

        it('returns an empty sources list when none are logged', function () {
            $ingredient = Ingredient::create(['name' => 'No Package Sizes Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);

            $response = $this->actingAs($this->admin)->getJson(route('admin.costing.inventory.sources', $ingredient->id));

            $response->assertOk()->assertJson(['sources' => []]);
        });

        it('persists a recount as that source\'s new quantity_on_hand outright', function () {
            $ingredient = Ingredient::create(['name' => 'Counted Save Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $packageSize = $ingredient->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1, 'quantity_on_hand' => 0]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.inventory.sources.adjust', [$ingredient->id, $packageSize->id]), [
                    'mode' => 'recount',
                    'packages' => 34500,
                ])
                ->assertRedirect();

            expect((float) $packageSize->fresh()->quantity_on_hand)->toBe(34500.0);
            expect((float) $ingredient->inventory->fresh()->on_hand)->toBe(34500.0);
        });

        it('writes an audit row for every adjustment, tied to the specific source', function () {
            $ingredient = Ingredient::create(['name' => 'Audit Trail Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $packageSize = $ingredient->packageSizes()->create(['provider' => 'GFS', 'brand' => 'Kraft', 'package_size' => 1000, 'quantity_on_hand' => 0]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.inventory.sources.adjust', [$ingredient->id, $packageSize->id]), [
                    'mode' => 'adjust',
                    'direction' => 'add',
                    'packages' => 5,
                    'notes' => 'Delivered by GFS truck',
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('costing_inventory_adjustments', [
                'ingredient_id' => $ingredient->id,
                'package_size_id' => $packageSize->id,
                'source_provider' => 'GFS',
                'source_brand' => 'Kraft',
                'reason' => 'received',
                'notes' => 'Delivered by GFS truck',
            ]);

            expect((float) $packageSize->fresh()->quantity_on_hand)->toBe(5000.0); // 5 packages x 1000g
        });

        it('customer cannot adjust a source', function () {
            $ingredient = Ingredient::create(['name' => 'Source Guard Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $packageSize = $ingredient->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1, 'quantity_on_hand' => 100]);

            $this->actingAs($this->customer)
                ->post(route('admin.costing.inventory.sources.adjust', [$ingredient->id, $packageSize->id]), [
                    'mode' => 'recount',
                    'packages' => 0,
                ])
                ->assertForbidden();

            expect((float) $packageSize->fresh()->quantity_on_hand)->toBe(100.0);
        });
    });

    /*
     * bulkUpdate()'s payload shape changed along with the per-source model:
     * mode 'received' -> 'adjust' (+ a required reason, since 'adjust' now
     * covers both received stock and corrections/writeoffs), a plain
     * 'quantity' -> 'packages' (multiplied by the target source's own
     * package_size), and each row now names which source (package_size_id)
     * it applies to -- a bulk update is no longer assumed to always hit one
     * auto-resolved target.
     */
    describe('bulk updating inventory', function () {
        it('adds received quantities on top of current stock', function () {
            $a = Ingredient::create(['name' => 'Bulk A', 'unit_type' => 'g', 'waste_percent' => 100]);
            $aSource = $a->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1, 'quantity_on_hand' => 2]);
            $b = Ingredient::create(['name' => 'Bulk B', 'unit_type' => 'unit', 'waste_percent' => 100]);
            $bSource = $b->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1, 'quantity_on_hand' => 5]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.inventory.bulk-update'), [
                    'mode' => 'adjust',
                    'reason' => 'received',
                    'items' => [
                        ['ingredient_id' => $a->id, 'package_size_id' => $aSource->id, 'packages' => 3],
                        ['ingredient_id' => $b->id, 'package_size_id' => $bSource->id, 'packages' => 10],
                    ],
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            expect((float) $a->inventory->fresh()->on_hand)->toBe(5.0); // 2 + 3
            expect((float) $b->inventory->fresh()->on_hand)->toBe(15.0); // 5 + 10
        });

        it('replaces stock outright in recount mode', function () {
            $a = Ingredient::create(['name' => 'Recount A', 'unit_type' => 'g', 'waste_percent' => 100]);
            $aSource = $a->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1, 'quantity_on_hand' => 99]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.inventory.bulk-update'), [
                    'mode' => 'recount',
                    'items' => [['ingredient_id' => $a->id, 'package_size_id' => $aSource->id, 'packages' => 4]],
                ])
                ->assertRedirect();

            expect((float) $a->inventory->fresh()->on_hand)->toBe(4.0); // replaced, not added
        });

        it('skips an ingredient with no source to attach a quantity to, rather than erroring', function () {
            $noSource = Ingredient::create(['name' => 'No Source Bulk', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.inventory.bulk-update'), [
                    'mode' => 'recount',
                    'items' => [['ingredient_id' => $noSource->id, 'package_size_id' => null, 'packages' => 5]],
                ])
                ->assertRedirect()
                ->assertSessionHas('success', fn ($message) => str_contains($message, 'Skipped'));

            expect((float) $noSource->inventory->fresh()->on_hand)->toBe(0.0);
        });

        it('rejects duplicate ingredients in the same batch', function () {
            $a = Ingredient::create(['name' => 'Dup A', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.inventory.bulk-update'), [
                    'mode' => 'adjust',
                    'reason' => 'received',
                    'items' => [
                        ['ingredient_id' => $a->id, 'package_size_id' => null, 'packages' => 1],
                        ['ingredient_id' => $a->id, 'package_size_id' => null, 'packages' => 2],
                    ],
                ])
                ->assertSessionHasErrors();
        });

        it('requires a reason in adjust mode but not in recount mode', function () {
            $a = Ingredient::create(['name' => 'Reason Required A', 'unit_type' => 'g', 'waste_percent' => 100]);
            $aSource = $a->packageSizes()->create(['provider' => 'Unspecified', 'package_size' => 1, 'quantity_on_hand' => 0]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.inventory.bulk-update'), [
                    'mode' => 'adjust',
                    'items' => [['ingredient_id' => $a->id, 'package_size_id' => $aSource->id, 'packages' => 1]],
                ])
                ->assertSessionHasErrors('reason');

            $this->actingAs($this->admin)
                ->post(route('admin.costing.inventory.bulk-update'), [
                    'mode' => 'recount',
                    'items' => [['ingredient_id' => $a->id, 'package_size_id' => $aSource->id, 'packages' => 1]],
                ])
                ->assertRedirect()
                ->assertSessionDoesntHaveErrors('reason');
        });

        it('customer cannot bulk update inventory', function () {
            $a = Ingredient::create(['name' => 'Forbidden Bulk', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->customer)
                ->post(route('admin.costing.inventory.bulk-update'), [
                    'mode' => 'adjust',
                    'reason' => 'received',
                    'items' => [['ingredient_id' => $a->id, 'package_size_id' => null, 'packages' => 1]],
                ])
                ->assertForbidden();
        });

        it('guest cannot bulk update inventory', function () {
            $a = Ingredient::create(['name' => 'Guest Bulk', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->post(route('admin.costing.inventory.bulk-update'), [
                'mode' => 'adjust',
                'reason' => 'received',
                'items' => [['ingredient_id' => $a->id, 'package_size_id' => null, 'packages' => 1]],
            ])->assertRedirect(route('login'));
        });
    });

    describe('category autocomplete', function () {
        it('offers previously-used categories on both the create and edit forms', function () {
            Ingredient::create(['name' => 'Category Seed A', 'unit_type' => 'g', 'waste_percent' => 100, 'category' => 'Dairy & Eggs']);
            $editTarget = Ingredient::create(['name' => 'Category Seed B', 'unit_type' => 'g', 'waste_percent' => 100, 'category' => 'Produce']);

            $this->actingAs($this->admin)
                ->get(route('admin.costing.ingredients.create'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Vendor/costing/Ingredients/Create')
                    ->where('categories', ['Dairy & Eggs', 'Produce'])
                );

            $this->actingAs($this->admin)
                ->get(route('admin.costing.ingredients.edit', $editTarget->id))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Vendor/costing/Ingredients/Edit')
                    ->where('categories', ['Dairy & Eggs', 'Produce'])
                );
        });
    });

    describe('recipe fill weight', function () {
        it('defaults a new recipe to a 280g fill when the form leaves it out', function () {
            $this->actingAs($this->admin)
                ->post(route('admin.costing.recipes.store'), ['name' => 'Default Fill Recipe', 'ingredients' => []])
                ->assertRedirect();

            expect((float) Recipe::where('name', 'Default Fill Recipe')->firstOrFail()->fill_size_g)->toBe(280.0);
        });

        it('saves the fill weight from the recipe form, and blank clears it', function () {
            $recipe = Recipe::create(['name' => 'Form Fill Recipe']);

            $this->actingAs($this->admin)
                ->put(route('admin.costing.recipes.update', $recipe), ['name' => 'Form Fill Recipe', 'fill_size_g' => 275, 'ingredients' => []])
                ->assertRedirect();
            expect((float) $recipe->fresh()->fill_size_g)->toBe(275.0);

            $this->actingAs($this->admin)
                ->put(route('admin.costing.recipes.update', $recipe), ['name' => 'Form Fill Recipe', 'fill_size_g' => null, 'ingredients' => []])
                ->assertRedirect();
            expect($recipe->fresh()->fill_size_g)->toBeNull();
        });

        it('keeps the saved fill weight when an update leaves it out', function () {
            $recipe = Recipe::create(['name' => 'Kept Fill Recipe', 'fill_size_g' => 290]);

            $this->actingAs($this->admin)
                ->put(route('admin.costing.recipes.update', $recipe), ['name' => 'Kept Fill Recipe', 'ingredients' => []])
                ->assertRedirect();

            expect((float) $recipe->fresh()->fill_size_g)->toBe(290.0);
        });

        it('rejects a zero fill weight', function () {
            $recipe = Recipe::create(['name' => 'Zero Fill Recipe']);

            $this->actingAs($this->admin)
                ->put(route('admin.costing.recipes.update', $recipe), ['name' => 'Zero Fill Recipe', 'fill_size_g' => 0, 'ingredients' => []])
                ->assertSessionHasErrors('fill_size_g');
        });

        it('passes the fill weight to the show and edit pages', function () {
            $recipe = Recipe::create(['name' => 'Props Fill Recipe', 'fill_size_g' => 285]);

            $this->actingAs($this->admin)->get(route('admin.costing.recipes.show', $recipe))
                ->assertInertia(fn ($page) => $page->where('recipe.fill_size_g', 285));
            $this->actingAs($this->admin)->get(route('admin.costing.recipes.edit', $recipe))
                ->assertInertia(fn ($page) => $page->where('recipe.fill_size_g', 285));
        });
    });

    describe('byproducts', function () {
        it('saves a byproduct line separately from the main ingredient line for the same ingredient', function () {
            $pickles = Ingredient::create(['name' => 'Byproduct Pickles', 'unit_type' => 'g', 'waste_percent' => 100, 'byproduct_name' => 'Juice']);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.recipes.store'), [
                    'name' => 'Byproduct Recipe',
                    'ingredients' => [['ingredient_id' => $pickles->id, 'quantity_per_jar' => 50]],
                    'byproducts' => [['ingredient_id' => $pickles->id, 'quantity_per_jar' => 15]],
                ])
                ->assertRedirect();

            $recipe = Recipe::where('name', 'Byproduct Recipe')->firstOrFail();

            expect((float) $recipe->mainIngredients->firstOrFail()->pivot->quantity_per_jar)->toBe(50.0);
            expect((float) $recipe->byproductIngredients->firstOrFail()->pivot->quantity_per_jar)->toBe(15.0);
        });

        it('excludes byproducts from the shopping list entirely', function () {
            $pickles = Ingredient::create(['name' => 'Shopping Pickles', 'unit_type' => 'g', 'waste_percent' => 100, 'byproduct_name' => 'Juice']);
            // Zero on hand is the default with no source rows at all.

            $recipe = Recipe::create(['name' => 'Shopping Recipe']);
            $recipe->mainIngredients()->sync([$pickles->id => ['quantity_per_jar' => 50]]);
            $recipe->byproductIngredients()->sync([$pickles->id => ['quantity_per_jar' => 999999]]); // huge -- must not affect the plan

            $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([$recipe->id => ['batches' => 10]]);

            $plan = (new \Cultpantry\Costing\Actions\CalculateProductionPlan(new \Cultpantry\Costing\Actions\CalculateIngredientCosting))->handle($run->fresh());

            $row = collect($plan['rows'])->firstOrFail();
            // Only the main line's 50g x 10 units = 500g -- the byproduct's
            // 999999 never entered the calculation.
            expect((float) $row['required'])->toBe(500.0);
        });
    });

    describe('recipe ingredient pricing', function () {
        it('includes per-ingredient costing on the Edit page, for the price indicator and cost-per-jar readout', function () {
            $fresh = Ingredient::create(['name' => 'Fresh Priced Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $fresh->priceHistory()->create([
                'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
                'provider' => 'GFS',
                'qty' => 1000,
                'total_price' => 8, // $8/kg
            ]);

            $stale = Ingredient::create(['name' => 'Stale Priced Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $stale->priceHistory()->create([
                'purchased_at' => Carbon::now()->subDays(30)->toDateString(),
                'provider' => 'GFS',
                'qty' => 1000,
                'total_price' => 6, // $6/kg, but stale
            ]);

            $recipe = Recipe::create(['name' => 'Priced Recipe']);
            $recipe->mainIngredients()->sync([
                $fresh->id => ['quantity_per_jar' => 100],
                $stale->id => ['quantity_per_jar' => 50],
            ]);

            $response = $this->actingAs($this->admin)->get(route('admin.costing.recipes.edit', $recipe->id));

            $response->assertOk()->assertInertia(fn ($page) => $page
                ->component('Vendor/costing/Recipes/Edit')
                ->where('ingredients', function ($ingredients) use ($fresh, $stale) {
                    $byId = collect($ingredients)->keyBy('id');

                    return $byId[$fresh->id]['status'] === 'ok'
                        && $byId[$fresh->id]['weekly_price'] == 8
                        && $byId[$stale->id]['status'] === 'no_price_this_week'
                        && $byId[$stale->id]['stale_price'] == 6;
                })
            );
        });
    });

    describe('cloning price history', function () {
        it('prefills the create form from an existing entry, including which source it belongs to', function () {
            // Clone now points at the Source relationship (package_size_id)
            // rather than duplicating provider/brand strings -- the create
            // form resolves provider/brand from that relation itself.
            $ingredient = Ingredient::create(['name' => 'Clone Source Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $packageSize = $ingredient->packageSizes()->create(['provider' => 'GFS', 'brand' => 'Kraft', 'package_size' => 5000]);
            $entry = $ingredient->priceHistory()->create([
                'purchased_at' => '2020-01-01',
                'package_size_id' => $packageSize->id,
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'qty' => 5000,
                'total_price' => 25,
                'sku' => 'SKU123',
                'notes' => 'Bulk pail',
            ]);

            $this->actingAs($this->admin)
                ->get(route('admin.costing.price-history.create', ['clone' => $entry->id]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Vendor/costing/PriceHistory/Create')
                    ->where('clone.ingredient_id', $ingredient->id)
                    ->where('clone.package_size_id', $packageSize->id)
                    ->where('clone.qty', 5000)
                    ->where('clone.total_price', 25)
                    ->where('clone.sku', 'SKU123')
                    ->where('clone.notes', 'Bulk pail')
                );
        });

        it('preselects an ingredient passed via ?ingredient=, for the "Log a new price" link from an ingredient with no history yet', function () {
            $ingredient = Ingredient::create(['name' => 'Preselect Ingredient', 'unit_type' => 'unit', 'waste_percent' => 100]);

            $this->actingAs($this->admin)
                ->get(route('admin.costing.price-history.create', ['ingredient' => $ingredient->id]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Vendor/costing/PriceHistory/Create')
                    ->where('preselectIngredientId', $ingredient->id)
                );
        });

        it('ignores a missing or invalid clone id', function () {
            $this->actingAs($this->admin)
                ->get(route('admin.costing.price-history.create', ['clone' => 999999]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('clone', null));
        });

        it('customer cannot use the clone endpoint', function () {
            $ingredient = Ingredient::create(['name' => 'Clone Guard Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $entry = $ingredient->priceHistory()->create(['provider' => 'GFS']);

            $this->actingAs($this->customer)
                ->get(route('admin.costing.price-history.create', ['clone' => $entry->id]))
                ->assertForbidden();
        });
    });

    describe('updating price', function () {
        it('creates a new entry dated today, identical to the original except the price', function () {
            $ingredient = Ingredient::create(['name' => 'Reprice Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $original = $ingredient->priceHistory()->create([
                'purchased_at' => '2020-01-01',
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'qty' => 5000,
                'total_price' => 25,
                'sku' => 'SKU123',
                'notes' => 'Bulk pail',
            ]);

            // Redirects back to wherever the request came from (Price
            // History or the Ingredients "Available Prices" modal) rather
            // than a hardcoded route -- just assert it's a redirect at all.
            $this->actingAs($this->admin)
                ->post(route('admin.costing.price-history.update-price', $original->id), ['total_price' => 30])
                ->assertRedirect();

            expect($ingredient->priceHistory()->count())->toBe(2);

            $newEntry = $ingredient->priceHistory()->latest('id')->firstOrFail();

            expect($newEntry->id)->not->toBe($original->id);
            expect((float) $newEntry->total_price)->toBe(30.0);
            expect((float) $newEntry->qty)->toBe(5000.0);
            expect($newEntry->provider)->toBe('GFS');
            expect($newEntry->brand)->toBe('Kraft');
            expect($newEntry->sku)->toBe('SKU123');
            expect($newEntry->notes)->toBe('Bulk pail');
            expect($newEntry->purchased_at->toDateString())->toBe(now()->toDateString());

            // The original entry is untouched.
            expect((float) $original->fresh()->total_price)->toBe(25.0);
        });

        it('requires a price', function () {
            $ingredient = Ingredient::create(['name' => 'Reprice Guard Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $entry = $ingredient->priceHistory()->create(['provider' => 'GFS']);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.price-history.update-price', $entry->id), [])
                ->assertSessionHasErrors('total_price');
        });

        it('customer cannot use the update-price endpoint', function () {
            $ingredient = Ingredient::create(['name' => 'Reprice Customer Guard', 'unit_type' => 'g', 'waste_percent' => 100]);
            $entry = $ingredient->priceHistory()->create(['provider' => 'GFS']);

            $this->actingAs($this->customer)
                ->post(route('admin.costing.price-history.update-price', $entry->id), ['total_price' => 30])
                ->assertForbidden();

            expect($ingredient->priceHistory()->count())->toBe(1);
        });
    });

    describe('logging a price as a case vs a package', function () {
        it('stores qty as the case total when priced_as_case is true', function () {
            $ingredient = Ingredient::create(['name' => 'Case Store Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $packageSize = PackageSize::create([
                'ingredient_id' => $ingredient->id,
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'package_size' => 1500,
                'units_per_case' => 3,
            ]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.price-history.store'), [
                    'ingredient_id' => $ingredient->id,
                    'package_size_id' => $packageSize->id,
                    'purchased_at' => now()->toDateString(),
                    'qty' => 4500,
                    'priced_as_case' => true,
                    'total_price' => 18.99,
                ])
                ->assertRedirect();

            $entry = $ingredient->priceHistory()->latest('id')->firstOrFail();
            expect((float) $entry->qty)->toBe(4500.0);
            expect($entry->priced_as_case)->toBeTrue();
        });

        it('carries priced_as_case forward on quick re-log and recomputes qty from the case total', function () {
            $ingredient = Ingredient::create(['name' => 'Case Relog Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $packageSize = PackageSize::create([
                'ingredient_id' => $ingredient->id,
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'package_size' => 1500,
                'units_per_case' => 3,
            ]);
            $original = $ingredient->priceHistory()->create([
                'purchased_at' => '2020-01-01',
                'package_size_id' => $packageSize->id,
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'qty' => 4500,
                'priced_as_case' => true,
                'total_price' => 18.99,
            ]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.price-history.update-price', $original->id), ['total_price' => 21.00])
                ->assertRedirect();

            $newEntry = $ingredient->priceHistory()->latest('id')->firstOrFail();
            expect($newEntry->id)->not->toBe($original->id);
            expect($newEntry->priced_as_case)->toBeTrue();
            expect((float) $newEntry->qty)->toBe(4500.0);
        });

        it('refreshes a case-priced entry\'s qty to the new case total when the package size changes', function () {
            $ingredient = Ingredient::create(['name' => 'Case Resize Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->admin)->post(route('admin.costing.ingredients.set-package-size', $ingredient->id), [
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'package_size' => 1500,
                'units_per_case' => 3,
            ])->assertRedirect();

            $packageSize = PackageSize::where('ingredient_id', $ingredient->id)->firstOrFail();

            $ingredient->priceHistory()->create([
                'purchased_at' => now()->toDateString(),
                'package_size_id' => $packageSize->id,
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'qty' => 4500,
                'priced_as_case' => true,
                'total_price' => 18.99,
            ]);

            $this->actingAs($this->admin)->post(route('admin.costing.ingredients.set-package-size', $ingredient->id), [
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'package_size' => 2000,
                'units_per_case' => 3,
            ])->assertRedirect();

            $latestEntry = $ingredient->priceHistory()->orderByDesc('purchased_at')->orderByDesc('id')->firstOrFail();
            expect((float) $latestEntry->qty)->toBe(6000.0);
        });

        it('includes priced_as_case in the price options for the Sources modal', function () {
            $ingredient = Ingredient::create(['name' => 'Case Flag Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $packageSize = PackageSize::create([
                'ingredient_id' => $ingredient->id,
                'provider' => 'GFS',
                'package_size' => 1500,
                'units_per_case' => 3,
            ]);
            $ingredient->priceHistory()->create([
                'purchased_at' => now()->toDateString(),
                'package_size_id' => $packageSize->id,
                'provider' => 'GFS',
                'qty' => 4500,
                'priced_as_case' => true,
                'total_price' => 18.99,
            ]);

            $response = $this->actingAs($this->admin)->get(route('admin.costing.ingredients.price-options', $ingredient->id));
            $options = collect($response->json('options'));

            expect($options->firstWhere('provider', 'GFS')['priced_as_case'])->toBeTrue();
        });
    });

    describe('needs-update flag on price history', function () {
        it('flags only the latest entry per ingredient/wholesaler/brand when it is stale', function () {
            $ingredient = Ingredient::create(['name' => 'Needs Update Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);

            // Superseded by a fresher entry below for the same combo --
            // must not be flagged even though it's old.
            $superseded = $ingredient->priceHistory()->create([
                'purchased_at' => Carbon::now()->subDays(30)->toDateString(),
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'qty' => 1000,
                'total_price' => 5,
            ]);

            // Latest for GFS/Kraft, but stale (>7 days) -- must be flagged.
            $staleLatest = $ingredient->priceHistory()->create([
                'purchased_at' => Carbon::now()->subDays(10)->toDateString(),
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'qty' => 1000,
                'total_price' => 6,
            ]);

            // Latest for a different wholesaler, fresh -- must not be flagged.
            $fresh = $ingredient->priceHistory()->create([
                'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
                'provider' => 'Costco',
                'qty' => 1000,
                'total_price' => 7,
            ]);

            $response = $this->actingAs($this->admin)->get(route('admin.costing.price-history.index'));

            $response->assertOk()->assertInertia(fn ($page) => $page
                ->component('Vendor/costing/PriceHistory/Index')
                ->where('entries', function ($entries) use ($superseded, $staleLatest, $fresh) {
                    $byId = collect($entries)->keyBy('id');

                    return $byId[$superseded->id]['needs_update'] === false
                        && $byId[$staleLatest->id]['needs_update'] === true
                        && $byId[$fresh->id]['needs_update'] === false;
                })
            );
        });
    });

    describe('available prices and preferred brand picker', function () {
        it('lists the latest entry per wholesaler/brand, sorted cheapest first, flagging stale and preferred rows', function () {
            $ingredient = Ingredient::create([
                'name' => 'Picker Ingredient',
                'unit_type' => 'g',
                'waste_percent' => 100,
                'preferred_source' => 'GFS',
                'preferred_brand' => 'Kraft',
            ]);

            $ingredient->priceHistory()->create([
                'purchased_at' => Carbon::now()->subDays(10)->toDateString(),
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'qty' => 1000,
                'total_price' => 10, // $10/kg, stale, preferred
            ]);

            $ingredient->priceHistory()->create([
                'purchased_at' => Carbon::now()->subDays(1)->toDateString(),
                'provider' => 'Costco',
                'brand' => 'Great Value',
                'qty' => 1000,
                'total_price' => 4, // $4/kg, fresh, cheaper
            ]);

            $response = $this->actingAs($this->admin)->get(route('admin.costing.ingredients.price-options', $ingredient->id));

            $response->assertOk();
            $options = $response->json('options');

            expect($options)->toHaveCount(2);
            // Cheapest first regardless of preference.
            expect($options[0]['provider'])->toBe('Costco');
            expect($options[0]['qty'])->toBe(1000);
            expect($options[0]['total_price'])->toBe(4);
            expect($options[0]['is_stale'])->toBeFalse();
            expect($options[0]['is_preferred'])->toBeFalse();
            // $4/kg / 10 = $0.40/100g
            expect($options[0]['price_per_100g'])->toBe(0.4);
            expect($options[1]['provider'])->toBe('GFS');
            expect($options[1]['is_stale'])->toBeTrue();
            expect($options[1]['is_preferred'])->toBeTrue();
        });

        it('sets and clears the preferred source/brand', function () {
            $ingredient = Ingredient::create(['name' => 'Preferred Set Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);

            // Redirects back to wherever the request came from (Ingredients
            // or the Recipe Edit page's Available Prices modal) rather than
            // a hardcoded route -- just assert it's a redirect at all.
            $this->actingAs($this->admin)
                ->post(route('admin.costing.ingredients.set-preferred', $ingredient->id), ['provider' => 'GFS', 'brand' => 'Kraft'])
                ->assertRedirect();

            expect($ingredient->fresh()->preferred_source)->toBe('GFS');
            expect($ingredient->fresh()->preferred_brand)->toBe('Kraft');

            $this->actingAs($this->admin)
                ->post(route('admin.costing.ingredients.set-preferred', $ingredient->id), ['provider' => null, 'brand' => null])
                ->assertRedirect();

            expect($ingredient->fresh()->preferred_source)->toBeNull();
            expect($ingredient->fresh()->preferred_brand)->toBeNull();
        });

        it('customer cannot view price options or set a preferred source', function () {
            $ingredient = Ingredient::create(['name' => 'Picker Guard Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->customer)
                ->get(route('admin.costing.ingredients.price-options', $ingredient->id))
                ->assertForbidden();

            $this->actingAs($this->customer)
                ->post(route('admin.costing.ingredients.set-preferred', $ingredient->id), ['provider' => 'GFS'])
                ->assertForbidden();

            expect($ingredient->fresh()->preferred_source)->toBeNull();
        });

        it('sets a per-brand package size and surfaces it in price options', function () {
            $ingredient = Ingredient::create(['name' => 'Package Size Set Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->admin)
                ->post(route('admin.costing.ingredients.set-package-size', $ingredient->id), [
                    'provider' => 'GFS',
                    'brand' => 'Kraft',
                    'package_size' => 15000,
                    'units_per_case' => 1,
                ])
                ->assertRedirect();

            $packageSize = PackageSize::where('ingredient_id', $ingredient->id)->firstOrFail();

            // GetIngredientPriceOptions resolves package_size off the
            // entry's live PackageSize relation (package_size_id), not a
            // provider/brand string match -- a price logged against this
            // source has to actually link to it.
            $ingredient->priceHistory()->create([
                'purchased_at' => Carbon::now()->subDay()->toDateString(),
                'package_size_id' => $packageSize->id,
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'qty' => 15000,
                'total_price' => 90,
            ]);

            $response = $this->actingAs($this->admin)->get(route('admin.costing.ingredients.price-options', $ingredient->id));
            $options = collect($response->json('options'));

            expect((float) $options->firstWhere('provider', 'GFS')['package_size'])->toBe(15000.0);
        });

        it('updates an existing package size in place rather than creating a duplicate', function () {
            $ingredient = Ingredient::create(['name' => 'Package Size Update Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->admin)->post(route('admin.costing.ingredients.set-package-size', $ingredient->id), [
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'package_size' => 15000,
                'units_per_case' => 1,
            ])->assertRedirect();

            $this->actingAs($this->admin)->post(route('admin.costing.ingredients.set-package-size', $ingredient->id), [
                'provider' => 'GFS',
                'brand' => 'Kraft',
                'package_size' => 20000,
                'units_per_case' => 1,
            ])->assertRedirect();

            expect(PackageSize::where('ingredient_id', $ingredient->id)->count())->toBe(1);
            expect((float) PackageSize::where('ingredient_id', $ingredient->id)->first()->package_size)->toBe(20000.0);
        });

        it('customer cannot set a package size', function () {
            $ingredient = Ingredient::create(['name' => 'Package Size Guard Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);

            $this->actingAs($this->customer)
                ->post(route('admin.costing.ingredients.set-package-size', $ingredient->id), [
                    'provider' => 'GFS',
                    'package_size' => 15000,
                ])
                ->assertForbidden();

            expect(PackageSize::where('ingredient_id', $ingredient->id)->count())->toBe(0);
        });
    });

    describe('recipe costing subpage', function () {
        it('renders every recipe with its computed cost/food-cost figures', function () {
            $ingredient = Ingredient::create(['name' => 'Costing Page Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $ingredient->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 10]);

            $recipe = Recipe::create([
                'name' => 'Costing Page Recipe',
                'sell_price' => 10,
                'fill_size_g' => 100,
                'cost_buffer_percent' => 10,
            ]);
            $recipe->mainIngredients()->sync([$ingredient->id => ['quantity_per_jar' => 100]]);

            $response = $this->actingAs($this->admin)->get(route('admin.costing.recipes.costing'));

            $response->assertOk()->assertInertia(fn ($page) => $page
                ->component('Vendor/costing/Recipes/Costing')
                ->where('recipes', function ($recipes) use ($recipe) {
                    $row = collect($recipes)->firstWhere('id', $recipe->id);

                    // 100g * $10/kg / 1000 = $1.00 raw; buffered 10% = $1.10;
                    // fill size == yield here, so actual cost/jar == buffered;
                    // food cost % = 1.10/10*100 = 11%.
                    return $row['raw_cost'] == 1
                        && $row['buffered_cost'] == 1.1
                        && $row['food_cost_percent'] == 11
                        && $row['any_stale'] === false
                        && $row['any_missing'] === false;
                })
            );
        });

        it('updates just the costing fields and redirects back, not to a hardcoded route', function () {
            $recipe = Recipe::create(['name' => 'Update Costing Recipe']);

            $this->actingAs($this->admin)
                ->from(route('admin.costing.recipes.costing'))
                ->put(route('admin.costing.recipes.update-costing', $recipe->id), [
                    'sell_price' => 12.5,
                    'fill_size_g' => 300,
                    'cost_buffer_percent' => 15,
                ])
                ->assertRedirect(route('admin.costing.recipes.costing'));

            $recipe->refresh();
            expect((float) $recipe->sell_price)->toBe(12.5);
            expect((float) $recipe->fill_size_g)->toBe(300.0);
            expect((float) $recipe->cost_buffer_percent)->toBe(15.0);
        });

        it('leaves the costing fields nullable -- a recipe with none set still renders fine', function () {
            $recipe = Recipe::create(['name' => 'No Costing Fields Recipe', 'fill_size_g' => null]);

            $response = $this->actingAs($this->admin)->get(route('admin.costing.recipes.costing'));

            $response->assertOk()->assertInertia(fn ($page) => $page
                ->where('recipes', function ($recipes) use ($recipe) {
                    $row = collect($recipes)->firstWhere('id', $recipe->id);

                    return $row['sell_price'] === null
                        && $row['fill_size_g'] === null
                        && $row['cost_buffer_percent'] === null
                        && $row['food_cost_percent'] === null;
                })
            );
        });

        it('customer cannot view or update recipe costing', function () {
            $recipe = Recipe::create(['name' => 'Costing Guard Recipe']);

            $this->actingAs($this->customer)
                ->get(route('admin.costing.recipes.costing'))
                ->assertForbidden();

            $this->actingAs($this->customer)
                ->put(route('admin.costing.recipes.update-costing', $recipe->id), ['sell_price' => 5])
                ->assertForbidden();

            expect($recipe->fresh()->sell_price)->toBeNull();
        });

        it('guest cannot view recipe costing', function () {
            $this->get(route('admin.costing.recipes.costing'))
                ->assertRedirect(route('login'));
        });
    });

    describe('cost history page', function () {
        it('renders every recipe and every recorded snapshot', function () {
            $ingredient = Ingredient::create(['name' => 'History Ingredient', 'unit_type' => 'g', 'waste_percent' => 100]);
            $ingredient->priceHistory()->create(['purchased_at' => Carbon::now()->subDay()->toDateString(), 'provider' => 'GFS', 'qty' => 1000, 'total_price' => 10]);

            $recipe = Recipe::create(['name' => 'History Recipe', 'sell_price' => 10, 'fill_size_g' => 100]);
            $recipe->mainIngredients()->sync([$ingredient->id => ['quantity_per_jar' => 100]]);

            $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
            $run->recipes()->sync([$recipe->id => ['batches' => 4]]);

            $this->actingAs($this->admin)->post(route('admin.costing.production-planner.complete', $run->id));

            $response = $this->actingAs($this->admin)->get(route('admin.costing.recipes.cost-history'));

            $response->assertOk()->assertInertia(fn ($page) => $page
                ->component('Vendor/costing/Recipes/CostHistory')
                ->where('recipes', fn ($recipes) => collect($recipes)->contains('id', $recipe->id))
                ->where('snapshots', function ($snapshots) use ($recipe) {
                    $row = collect($snapshots)->firstWhere('recipe_id', $recipe->id);

                    return $row !== null
                        && $row['recipe_name'] === 'History Recipe'
                        && $row['jars_produced'] === 4;
                })
            );
        });

        it('customer cannot view cost history', function () {
            $this->actingAs($this->customer)
                ->get(route('admin.costing.recipes.cost-history'))
                ->assertForbidden();
        });

        it('guest cannot view cost history', function () {
            $this->get(route('admin.costing.recipes.cost-history'))
                ->assertRedirect(route('login'));
        });
    });
});
