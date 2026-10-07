<?php

use App\Models\User;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Support\Carbon;

// The costing Create/Edit forms autosave (FORM_DESIGN.md → Autosave): the
// Create form's first `stay` save hands off to the new record's Edit page,
// Edit saves stay put without a success flash, and a record created this
// session can be discarded (hard-deleted) while it's fresh.

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

describe('ingredients', function () {
    it('hands a create autosave off to the edit page with step and created flags', function () {
        $response = $this->actingAs($this->admin)->post(route('admin.costing.ingredients.store', ['stay' => 1, 'step' => 0]), [
            'name' => 'Autosaved Flour',
            'unit_type' => 'g',
            'waste_percent' => 100,
        ]);

        $ingredient = Ingredient::where('name', 'Autosaved Flour')->firstOrFail();
        $response->assertRedirect(route('admin.costing.ingredients.edit', ['ingredient' => $ingredient, 'step' => 0, 'created' => 1]));
        $response->assertSessionMissing('success');
    });

    it('keeps an update autosave on the edit page without a success flash', function () {
        $ingredient = Ingredient::create(['name' => 'Salt', 'unit_type' => 'g', 'waste_percent' => 100]);

        $this->actingAs($this->admin)
            ->put(route('admin.costing.ingredients.update', [$ingredient, 'stay' => 1]), [
                'name' => 'Sea Salt',
                'unit_type' => 'g',
                'waste_percent' => 100,
            ])
            ->assertRedirect(route('admin.costing.ingredients.edit', $ingredient))
            ->assertSessionMissing('success');

        expect($ingredient->fresh()->name)->toBe('Sea Salt');
    });

    it('discards a fresh draft, freeing its name', function () {
        $ingredient = Ingredient::create(['name' => 'Draft Sugar', 'unit_type' => 'g', 'waste_percent' => 100]);

        $this->actingAs($this->admin)
            ->delete(route('admin.costing.ingredients.discard-draft', $ingredient))
            ->assertRedirect(route('admin.costing.ingredients.index'));

        expect(Ingredient::whereKey($ingredient->id)->exists())->toBeFalse();
    });

    it('refuses to discard an ingredient that is over a day old', function () {
        $ingredient = Ingredient::create(['name' => 'Old Sugar', 'unit_type' => 'g', 'waste_percent' => 100]);
        $ingredient->forceFill(['created_at' => Carbon::now()->subDays(2)])->save();

        $this->actingAs($this->admin)
            ->delete(route('admin.costing.ingredients.discard-draft', $ingredient))
            ->assertForbidden();
    });

    it('refuses to discard an ingredient a recipe already uses', function () {
        $ingredient = Ingredient::create(['name' => 'Used Sugar', 'unit_type' => 'g', 'waste_percent' => 100]);
        $recipe = Recipe::create(['name' => 'Jam']);
        $recipe->mainIngredients()->attach($ingredient->id, ['quantity_per_jar' => 10]);

        $this->actingAs($this->admin)
            ->delete(route('admin.costing.ingredients.discard-draft', $ingredient))
            ->assertForbidden();
    });

    it('refuses discard-draft for non-admins', function () {
        $ingredient = Ingredient::create(['name' => 'Guarded', 'unit_type' => 'g', 'waste_percent' => 100]);

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.costing.ingredients.discard-draft', $ingredient))
            ->assertForbidden();

        expect(Ingredient::whereKey($ingredient->id)->exists())->toBeTrue();
    });
});

describe('recipes', function () {
    it('hands a create autosave off to the edit page', function () {
        $response = $this->actingAs($this->admin)->post(route('admin.costing.recipes.store', ['stay' => 1, 'step' => 1]), [
            'name' => 'Autosaved Jam',
            'is_active' => true,
            'ingredients' => [],
            'byproducts' => [],
        ]);

        $recipe = Recipe::where('name', 'Autosaved Jam')->firstOrFail();
        $response->assertRedirect(route('admin.costing.recipes.edit', ['recipe' => $recipe, 'step' => 1, 'created' => 1]));
        $response->assertSessionMissing('success');
    });

    it('keeps an update autosave on the edit page without a success flash', function () {
        $recipe = Recipe::create(['name' => 'Jam']);

        $this->actingAs($this->admin)
            ->put(route('admin.costing.recipes.update', [$recipe, 'stay' => 1]), [
                'name' => 'Berry Jam',
                'ingredients' => [],
            ])
            ->assertRedirect(route('admin.costing.recipes.edit', $recipe))
            ->assertSessionMissing('success');
    });

    it('discards a fresh draft', function () {
        $recipe = Recipe::create(['name' => 'Draft Jam']);

        $this->actingAs($this->admin)
            ->delete(route('admin.costing.recipes.discard-draft', $recipe))
            ->assertRedirect(route('admin.costing.recipes.index'));

        expect(Recipe::whereKey($recipe->id)->exists())->toBeFalse();
    });

    it('refuses to discard a recipe a production run uses', function () {
        $recipe = Recipe::create(['name' => 'Planned Jam']);
        $run = ProductionRun::create(['batch_size' => 1, 'run_date' => Carbon::now()->toDateString()]);
        $run->recipes()->attach($recipe->id, ['batches' => 1]);

        $this->actingAs($this->admin)
            ->delete(route('admin.costing.recipes.discard-draft', $recipe))
            ->assertForbidden();
    });
});

describe('price history', function () {
    beforeEach(function () {
        $this->ingredient = Ingredient::create(['name' => 'Butter', 'unit_type' => 'g', 'waste_percent' => 100]);
        $this->source = PackageSize::create(['ingredient_id' => $this->ingredient->id, 'provider' => 'GFS', 'package_size' => 1000]);
        $this->payload = [
            'ingredient_id' => $this->ingredient->id,
            'package_size_id' => $this->source->id,
            'qty' => 1000,
            'total_price' => 12.5,
        ];
    });

    it('hands a Log a Price autosave (with step) off to the edit page', function () {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.costing.price-history.store', ['stay' => 1, 'step' => 0]), $this->payload);

        $entry = PriceHistoryEntry::latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.costing.price-history.edit', ['priceHistoryEntry' => $entry, 'step' => 0, 'created' => 1]));
        $response->assertSessionMissing('success');
    });

    it('keeps SourcesTable\'s inline log (stay, no step) on the calling page', function () {
        $this->actingAs($this->admin)
            ->from(route('admin.costing.ingredients.index'))
            ->post(route('admin.costing.price-history.store'), [...$this->payload, 'stay' => true])
            ->assertRedirect(route('admin.costing.ingredients.index'))
            ->assertSessionHas('success', 'Price logged.');
    });

    it('keeps an update autosave on the edit page without a success flash', function () {
        $entry = PriceHistoryEntry::create([...$this->payload, 'provider' => 'GFS']);

        $this->actingAs($this->admin)
            ->put(route('admin.costing.price-history.update', [$entry, 'stay' => 1]), [...$this->payload, 'total_price' => 13])
            ->assertRedirect(route('admin.costing.price-history.edit', $entry))
            ->assertSessionMissing('success');
    });

    it('discards a fresh draft and refuses an old one', function () {
        $fresh = PriceHistoryEntry::create([...$this->payload, 'provider' => 'GFS']);
        $old = PriceHistoryEntry::create([...$this->payload, 'provider' => 'GFS']);
        $old->forceFill(['created_at' => Carbon::now()->subDays(2)])->save();

        $this->actingAs($this->admin)
            ->delete(route('admin.costing.price-history.discard-draft', $fresh))
            ->assertRedirect(route('admin.costing.price-history.index'));
        expect(PriceHistoryEntry::whereKey($fresh->id)->exists())->toBeFalse();

        $this->actingAs($this->admin)
            ->delete(route('admin.costing.price-history.discard-draft', $old))
            ->assertForbidden();
    });
});

describe('show pages', function () {
    it('renders each record\'s show page for admins and refuses everyone else', function () {
        $ingredient = Ingredient::create(['name' => 'Shown Butter', 'unit_type' => 'g', 'waste_percent' => 100]);
        $source = PackageSize::create(['ingredient_id' => $ingredient->id, 'provider' => 'GFS', 'package_size' => 1000]);
        $entry = PriceHistoryEntry::create([
            'ingredient_id' => $ingredient->id,
            'package_size_id' => $source->id,
            'provider' => 'GFS',
            'qty' => 1000,
            'total_price' => 12.5,
            'purchased_at' => Carbon::now()->toDateString(),
        ]);
        $recipe = Recipe::create(['name' => 'Shown Jam']);
        $recipe->mainIngredients()->attach($ingredient->id, ['quantity_per_jar' => 10]);

        $routes = [
            route('admin.costing.ingredients.show', $ingredient) => 'Vendor/costing/Ingredients/Show',
            route('admin.costing.recipes.show', $recipe) => 'Vendor/costing/Recipes/Show',
            route('admin.costing.price-history.show', $entry) => 'Vendor/costing/PriceHistory/Show',
        ];

        foreach ($routes as $url => $component) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component, false));

            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        }
    });
});
