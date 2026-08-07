<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split from the column re-add (next migration) -- SQLite rebuilds the
     * whole table to drop a foreign key, and combining that with adding a
     * new one in the same Schema::table() call in one migration causes
     * Laravel's SQLite grammar to redundantly re-apply the new columns a
     * second time ("duplicate column" error). Two migrations avoids it,
     * matching this module's existing drop-then-add pattern (see
     * 2026_07_28_100004/100005).
     */
    public function up(): void
    {
        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->dropForeign(['package_size_id']);
            $table->dropColumn('package_size_id');
        });
    }

    public function down(): void
    {
        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->foreignId('package_size_id')->constrained('costing_ingredient_package_sizes')->cascadeOnDelete();
        });
    }
};
