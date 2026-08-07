<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sources can now be deleted (e.g. no longer buying from that
     * supplier). nullOnDelete (not cascade, which the dropped column had)
     * so deleting a source doesn't silently wipe its adjustment history --
     * an audit trail must never do that. source_provider/source_brand are
     * a snapshot taken at write time, so history stays fully readable
     * ("GFS -- Kraft") even after the row it pointed to is gone (mirrors
     * production_run_id's existing nullOnDelete reasoning on this table).
     */
    public function up(): void
    {
        // Two separate Schema::table() calls, not one -- adding a new
        // foreign-key column makes SQLite's grammar rebuild the whole table
        // (its ALTER TABLE can't add a constrained column directly).
        // Bundling the plain string columns into that same call made
        // Laravel *also* try to re-apply them afterward via individual
        // ALTER ADD COLUMN statements once the rebuild had already added
        // them, which fails with "duplicate column" even though the
        // rebuilt table is otherwise already correct.
        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->foreignId('package_size_id')->nullable()->after('ingredient_id')->constrained('costing_ingredient_package_sizes')->nullOnDelete();
        });

        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->string('source_provider')->after('package_size_id');
            $table->string('source_brand')->nullable()->after('source_provider');
        });

        // Backfill the snapshot for any rows written before this column
        // existed, from whatever their (still-linked) source currently is.
        // A per-row loop, not a single UPDATE ... JOIN -- SQLite rewrites a
        // joined update into an `UPDATE ... WHERE rowid IN (subquery)` form
        // where the joined table's columns aren't in scope for the SET
        // clause, so a raw cross-table SET fails outright on that driver.
        DB::table('costing_inventory_adjustments')
            ->whereNotNull('package_size_id')
            ->orderBy('id')
            ->each(function (object $adjustment) {
                $source = DB::table('costing_ingredient_package_sizes')->find($adjustment->package_size_id);

                if ($source) {
                    DB::table('costing_inventory_adjustments')
                        ->where('id', $adjustment->id)
                        ->update(['source_provider' => $source->provider, 'source_brand' => $source->brand]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->dropForeign(['package_size_id']);
            $table->dropColumn(['package_size_id', 'source_provider', 'source_brand']);
        });
    }
};
