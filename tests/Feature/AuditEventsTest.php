<?php

use App\Models\User;
use Cultpantry\Costing\Events\CostingRecordDeleted;
use Cultpantry\Costing\Events\CostingRecordSaved;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\InventoryAdjustment;
use Cultpantry\Costing\Models\PackageSize;
use Illuminate\Support\Facades\Event;

/*
 * The package's side of auditing: every action that changes costing data
 * dispatches CostingRecordSaved/Deleted. What the host does with them (its
 * Event log) is the host's to test.
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->ingredient = Ingredient::create(['name' => 'Salt', 'unit_type' => 'g', 'waste_percent' => 100]);
    $this->source = $this->ingredient->packageSizes()->create(['provider' => 'GFS', 'package_size' => 1000, 'quantity_on_hand' => 0]);
    Event::fake([CostingRecordSaved::class, CostingRecordDeleted::class]);
});

function savedFor(string $modelClass, string $action): \Illuminate\Support\Collection
{
    return Event::dispatched(CostingRecordSaved::class, fn ($e) => $e->modelClass === $modelClass && $e->action === $action)->map(fn ($args) => $args[0]);
}

it('dispatches an update for a package size change, with the old value', function () {
    $this->actingAs($this->admin)->post(route('admin.costing.ingredients.set-package-size', $this->ingredient), [
        'provider' => 'GFS', 'package_size' => 2000, 'units_per_case' => 1,
    ]);

    $changes = savedFor(PackageSize::class, 'updated')->sole()->changes;
    expect($changes['package_size']['old'])->toEqual(1000)
        ->and($changes['package_size']['new'])->toEqual(2000);
});

it('dispatches a create for a brand-new source', function () {
    $this->actingAs($this->admin)->post(route('admin.costing.ingredients.set-package-size', $this->ingredient), [
        'provider' => 'Sysco', 'package_size' => 500, 'units_per_case' => 1,
    ]);

    expect(savedFor(PackageSize::class, 'created'))->toHaveCount(1);
});

it('dispatches updates for preferred-source changes and source renames', function () {
    $this->actingAs($this->admin)->post(route('admin.costing.ingredients.set-preferred', $this->ingredient), ['provider' => 'GFS']);
    $this->actingAs($this->admin)->post(route('admin.costing.ingredients.sources.rename', [$this->ingredient, $this->source]), ['provider' => 'Sysco']);

    expect(savedFor(Ingredient::class, 'updated')->count())->toBeGreaterThanOrEqual(1)
        ->and(savedFor(PackageSize::class, 'updated')->sole()->context)->toHaveKey('price_entries_renamed');
});

it('dispatches one event per stock change, wherever it comes from', function () {
    $this->actingAs($this->admin)->post(route('admin.costing.inventory.sources.adjust', [$this->ingredient->id, $this->source->id]), [
        'mode' => 'adjust', 'direction' => 'add', 'packages' => 2,
    ]);
    $this->actingAs($this->admin)->post(route('admin.costing.inventory.sources.adjust', [$this->ingredient->id, $this->source->id]), [
        'mode' => 'recount', 'packages' => 0,
    ]);

    expect(savedFor(InventoryAdjustment::class, 'created'))->toHaveCount(2);
});

it('dispatches creates for a new inventory item, and a delete for a removed source', function () {
    $this->actingAs($this->admin)->post(route('admin.costing.inventory.items.store'), [
        'name' => 'Pepper', 'unit_type' => 'g', 'waste_percent' => 100, 'provider' => 'GFS', 'package_size' => 500,
    ]);
    $this->actingAs($this->admin)->delete(route('admin.costing.inventory.sources.destroy', [$this->ingredient->id, $this->source->id]));

    expect(savedFor(Ingredient::class, 'created'))->toHaveCount(1)
        ->and(savedFor(PackageSize::class, 'created'))->toHaveCount(1)
        ->and(Event::dispatched(CostingRecordDeleted::class, fn ($e) => $e->modelClass === PackageSize::class))->toHaveCount(1);
});
