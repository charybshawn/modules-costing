<?php

use Illuminate\Support\Facades\Route;

/*
 * Every action must be auditable. Each route this module registers that
 * changes data dispatches CostingRecordSaved / CostingRecordDeleted, which the host writes to its Event log. A new write route fails here until it does and
 * is added below.
 */
it('audits every route in this module that changes data', function () {
    $audited = [
        'DELETE admin/costing/ingredients/{ingredient}',
        'DELETE admin/costing/ingredients/{ingredient}/discard-draft',
        'DELETE admin/costing/inventory/{ingredient}/sources/{packageSize}',
        'DELETE admin/costing/price-history/{priceHistoryEntry}',
        'DELETE admin/costing/price-history/{priceHistoryEntry}/discard-draft',
        'DELETE admin/costing/production-planner/{productionRun}',
        'DELETE admin/costing/recipes/{recipe}',
        'DELETE admin/costing/recipes/{recipe}/discard-draft',
        // Import writes are audited per record; preview/repreview only read
        // the upload and change nothing.
        'POST admin/costing/data/import',
        'POST admin/costing/data/preview',
        'POST admin/costing/data/repreview',
        'POST admin/costing/ingredients',
        'POST admin/costing/ingredients/bulk-action',
        'POST admin/costing/ingredients/{ingredient}/duplicate',
        'POST admin/costing/ingredients/{ingredient}/package-size',
        'POST admin/costing/ingredients/{ingredient}/preferred',
        'POST admin/costing/ingredients/{ingredient}/sources/{packageSize}/rename',
        'POST admin/costing/inventory/bulk-update',
        'POST admin/costing/inventory/items',
        'POST admin/costing/inventory/{ingredient}/sources/{packageSize}',
        'POST admin/costing/kitchen-rentals/import',
        'POST admin/costing/kitchen-rentals/{kitchenRental}/attach-run',
        'POST admin/costing/kitchen-rentals/{kitchenRental}/create-run',
        'POST admin/costing/kitchen-rentals/{kitchenRental}/detach-run',
        'POST admin/costing/kitchen-rentals/{kitchenRental}/status',
        'POST admin/costing/price-history',
        'POST admin/costing/price-history/bulk-action',
        'POST admin/costing/price-history/{priceHistoryEntry}/update-price',
        'POST admin/costing/production-planner',
        'POST admin/costing/production-planner/bulk-action',
        'POST admin/costing/production-planner/{productionRun}/attach-rental',
        'POST admin/costing/production-planner/{productionRun}/complete',
        'POST admin/costing/production-planner/{productionRun}/detach-rental',
        'POST admin/costing/production-planner/{productionRun}/uncomplete',
        'POST admin/costing/recipes',
        'POST admin/costing/recipes/bulk-action',
        'PUT admin/costing/ingredients/{ingredient}',
        'PUT admin/costing/price-history/{priceHistoryEntry}',
        'PUT admin/costing/production-planner/{productionRun}',
        'PUT admin/costing/recipes/{recipe}',
        'PUT admin/costing/recipes/{recipe}/costing',
        'PUT admin/costing/settings',
    ];

    $writeRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getActionName(), 'Cultpantry\\Costing\\'))
        ->flatMap(fn ($r) => collect($r->methods())
            ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
            ->map(fn ($m) => "{$m} {$r->uri()}"))
        ->unique()->sort()->values();

    expect($writeRoutes->diff($audited)->values()->all())->toBe([])
        ->and(collect($audited)->diff($writeRoutes)->values()->all())->toBe([]);
});
