<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ingredient_id was still cascadeOnDelete() (and NOT NULL) from this
     * table's original migration -- the one FK on this table never updated
     * to match package_size_id's already-nullOnDelete() treatment (see
     * 2026_08_06_100005's docblock: "an audit trail must never [be
     * silently wiped]"). Deleting an Ingredient was silently destroying
     * its own inventory-adjustment history along with it. Column added,
     * backfilled, and swapped in rather than using ->change() -- avoiding
     * doctrine/dbal and matching this table's own established
     * drop-then-add migration style (see 2026_08_06_100004/100005) for
     * changing a foreign key's delete behaviour on SQLite.
     */
    public function up(): void
    {
        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->foreignId('ingredient_id_new')->nullable()->after('ingredient_id')->constrained('costing_ingredients')->nullOnDelete();
        });

        DB::table('costing_inventory_adjustments')->update(['ingredient_id_new' => DB::raw('ingredient_id')]);

        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->dropForeign(['ingredient_id']);
            $table->dropColumn('ingredient_id');
        });

        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->renameColumn('ingredient_id_new', 'ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->foreignId('ingredient_id_old')->nullable()->after('ingredient_id')->constrained('costing_ingredients')->cascadeOnDelete();
        });

        DB::table('costing_inventory_adjustments')->update(['ingredient_id_old' => DB::raw('ingredient_id')]);

        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->dropForeign(['ingredient_id']);
            $table->dropColumn('ingredient_id');
        });

        Schema::table('costing_inventory_adjustments', function (Blueprint $table) {
            $table->renameColumn('ingredient_id_old', 'ingredient_id');
        });
    }
};
