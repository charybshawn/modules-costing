<?php

namespace Cultpantry\Costing\Actions;

use App\Actions\SyncInventory;
use Cultpantry\Costing\Models\InventoryAdjustment;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\RecipeCostSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a completed production run: credits back every ingredient
 * quantity CompleteProductionRun deducted (via the InventoryAdjustment
 * audit trail it left behind, not a hard reset to a stored "before" value,
 * since other legitimate adjustments may have touched the same source
 * since), decrements finished-goods stock on any recipe's linked product by
 * the same actual_units CompleteProductionRun credited, deletes the frozen
 * cost snapshots that completion created, and clears completed_at --
 * returning the run fully to "Planned" so it's editable and re-completable,
 * rather than a separate "reversed" status.
 */
class UncompleteProductionRun
{
    public function __construct(
        private readonly RecordInventoryAdjustment $recordInventoryAdjustment,
    ) {}

    /**
     * @return array<int, string> human-readable warnings, one per source
     *     that was deleted after the original deduction and so couldn't be
     *     credited back automatically -- empty when everything reversed
     *     cleanly.
     */
    public function handle(ProductionRun $productionRun): array
    {
        return DB::transaction(fn () => $this->run($productionRun));
    }

    /**
     * @return array<int, string>
     */
    private function run(ProductionRun $productionRun): array
    {
        // Locked the same way CompleteProductionRun locks the run before
        // deducting -- guards against a concurrent double-undo the same way
        // that guards against a concurrent double-complete.
        $productionRun = ProductionRun::whereKey($productionRun->id)->lockForUpdate()->with('recipes.product')->firstOrFail();
        abort_if(!$productionRun->completed_at, 422, 'This run has not been completed.');

        $warnings = [];

        $deductions = InventoryAdjustment::where('production_run_id', $productionRun->id)
            ->where('reason', 'production_run')
            ->get();

        foreach ($deductions as $deduction) {
            $label = $deduction->source_brand ? "{$deduction->source_provider} -- {$deduction->source_brand}" : $deduction->source_provider;

            // Locked, not read off the adjustment row -- other legitimate
            // adjustments may have touched this same source since the
            // original deduction, so this credits back the deducted amount
            // rather than resetting to the stored on_hand_before, which
            // would clobber them. package_size_id is null when the source
            // was deleted after the deduction (nullOnDelete) -- nothing
            // live to credit back in that case.
            $packageSize = $deduction->package_size_id
                ? PackageSize::whereKey($deduction->package_size_id)->lockForUpdate()->first()
                : null;

            if (!$packageSize) {
                $warnings[] = "{$label} (source no longer exists -- adjust inventory manually)";
                continue;
            }

            $before = (float) $packageSize->quantity_on_hand;
            $after = $before - (float) $deduction->delta;

            $packageSize->update(['quantity_on_hand' => $after]);

            $this->recordInventoryAdjustment->handle(
                packageSize: $packageSize,
                reason: 'production_run_reversal',
                onHandBefore: $before,
                onHandAfter: $after,
                notes: "Reversal of run #{$productionRun->id} completion",
                productionRun: $productionRun,
            );
        }

        RecipeCostSnapshot::where('production_run_id', $productionRun->id)->delete();

        foreach ($productionRun->recipes as $recipe) {
            // Reverses exactly what CompleteProductionRun credited -- must
            // run before actual_units is nulled out below, since that's the
            // only record of how many units this recipe actually credited.
            if ($recipe->product_id && $recipe->pivot->actual_units !== null) {
                app(SyncInventory::class)->applyChange(
                    product: $recipe->product,
                    newQuantity: $recipe->product->stock_quantity - (int) $recipe->pivot->actual_units,
                    reason: SyncInventory::REASONS['PRODUCTION_RUN_REVERSAL'],
                    metadata: [
                        'production_run_id' => $productionRun->id,
                        'recipe_id' => $recipe->id,
                        'units' => (int) $recipe->pivot->actual_units,
                    ],
                );
            }

            $productionRun->recipes()->updateExistingPivot($recipe->id, ['actual_units' => null]);
        }

        $productionRun->update(['completed_at' => null]);

        return $warnings;
    }
}
