<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Links every existing price-history row to the Source (PackageSize)
     * it actually describes, using the same (ingredient, provider, brand)
     * identity IngredientController::setPackageSize() already upserts on --
     * so this naturally reuses an existing Source instead of creating a
     * duplicate. A per-row loop, not an UPDATE...JOIN -- SQLite rewrites a
     * joined update into a form where the joined table's columns aren't in
     * scope for SET (hit this same issue backfilling inventory adjustments
     * earlier).
     */
    public function up(): void
    {
        DB::table('costing_price_history')
            ->whereNull('package_size_id')
            ->orderBy('id')
            ->each(function (object $entry) {
                $source = DB::table('costing_ingredient_package_sizes')
                    ->where('ingredient_id', $entry->ingredient_id)
                    ->where('provider', $entry->provider)
                    ->where('brand', $entry->brand)
                    ->first();

                $sourceId = $source?->id ?? DB::table('costing_ingredient_package_sizes')->insertGetId([
                    'ingredient_id' => $entry->ingredient_id,
                    'provider' => $entry->provider,
                    'brand' => $entry->brand,
                    'package_size' => $entry->qty ?: 1,
                    'quantity_on_hand' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('costing_price_history')
                    ->where('id', $entry->id)
                    ->update(['package_size_id' => $sourceId]);
            });
    }

    public function down(): void
    {
        // Intentionally a no-op -- this only links existing rows to
        // existing/newly-created Sources, it doesn't change data that
        // needs reverting. package_size_id itself is dropped by the
        // migration that added it.
    }
};
