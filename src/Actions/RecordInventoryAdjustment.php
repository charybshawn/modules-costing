<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Events\CostingRecordSaved;
use Cultpantry\Costing\Models\InventoryAdjustment;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\ProductionRun;

/**
 * Writes one audit-trail row for a single change to one source's
 * (provider/brand) on-hand quantity. Called from every place a
 * PackageSize.quantity_on_hand actually changes -- InventoryController's
 * per-source adjust/recount endpoint, ::bulkUpdate(), and
 * CompleteProductionRun -- so the delta math and row shape can't drift
 * between them.
 */
class RecordInventoryAdjustment
{
    public function handle(
        PackageSize $packageSize,
        string $reason,
        float $onHandBefore,
        float $onHandAfter,
        ?string $notes = null,
        ?ProductionRun $productionRun = null,
        ?int $userId = null,
    ): void {
        $adjustment = InventoryAdjustment::create([
            'ingredient_id' => $packageSize->ingredient_id,
            'package_size_id' => $packageSize->id,
            // Snapshotted, not just left to the relation -- stays readable
            // even after the source itself is later deleted.
            'source_provider' => $packageSize->provider,
            'source_brand' => $packageSize->brand,
            'production_run_id' => $productionRun?->id,
            'user_id' => $userId,
            'reason' => $reason,
            'delta' => $onHandAfter - $onHandBefore,
            'on_hand_before' => $onHandBefore,
            'on_hand_after' => $onHandAfter,
            'notes' => $notes,
        ]);

        // Every stock change (counts, corrections, deliveries, production
        // runs) reaches the host's audit log through this one call.
        event(CostingRecordSaved::forCreated($adjustment, $userId, array_filter([
            'reason' => $reason,
            'production_run_id' => $productionRun?->id,
        ], fn ($v) => $v !== null)));
    }
}
