<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fully superseded by per-source tracking on
     * costing_ingredient_package_sizes -- InventoryItem::on_hand now sums
     * that table instead of reading these. Must run after the previous
     * migration has moved their values onto the Unspecified source.
     */
    public function up(): void
    {
        Schema::table('costing_inventory', function (Blueprint $table) {
            $table->dropColumn(['unit_size', 'units_on_hand', 'counted_on_hand']);
        });
    }

    public function down(): void
    {
        Schema::table('costing_inventory', function (Blueprint $table) {
            $table->decimal('unit_size', 12, 2)->default(0);
            $table->decimal('units_on_hand', 10, 2)->default(0);
            $table->decimal('counted_on_hand', 12, 2)->nullable();
        });
    }
};
