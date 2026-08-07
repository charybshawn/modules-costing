<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Moves every ingredient's current aggregate on_hand (however it was
     * being computed -- counted_on_hand if set, else unit_size x
     * units_on_hand) onto an auto-created "Unspecified" source row, since
     * there's no way to know the true historical breakdown by brand --
     * real per-source numbers start accumulating from the next actual
     * recount. Every ingredient gets this fallback row regardless of
     * current stock level, so "every adjustment must pick a source" always
     * has something to pick even for a fresh/zero-stock ingredient.
     * Must run before the follow-up migration drops the legacy columns
     * this reads from.
     */
    public function up(): void
    {
        $inventoryRows = DB::table('costing_inventory')->get();

        foreach ($inventoryRows as $row) {
            $onHand = $row->counted_on_hand !== null
                ? (float) $row->counted_on_hand
                : (float) $row->unit_size * (float) $row->units_on_hand;

            $existing = DB::table('costing_ingredient_package_sizes')
                ->where('ingredient_id', $row->ingredient_id)
                ->where('provider', 'Unspecified')
                ->whereNull('brand')
                ->first();

            if ($existing) {
                DB::table('costing_ingredient_package_sizes')
                    ->where('id', $existing->id)
                    ->update(['quantity_on_hand' => (float) $existing->quantity_on_hand + $onHand]);
            } else {
                DB::table('costing_ingredient_package_sizes')->insert([
                    'ingredient_id' => $row->ingredient_id,
                    'provider' => 'Unspecified',
                    'brand' => null,
                    'package_size' => 1,
                    'quantity_on_hand' => $onHand,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Deliberately irreversible -- same reasoning as the
        // weight_per_unit backfill this mirrors (2026_07_28_100004): no way
        // to tell which quantity_on_hand came from this migration vs a real
        // recount since, so reverting risks discarding real data.
    }
};
