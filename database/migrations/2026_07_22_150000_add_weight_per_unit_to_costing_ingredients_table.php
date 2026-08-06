<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            // Canonical "how much does one unit of this ingredient weigh"
            // (grams, or a count for unit_type 'unit'). Nullable -- used as
            // a reliable fallback for the Units-to-Buy calculation when
            // Price History has no fresh entry to derive a purchase size
            // from, and to seed a new ingredient's initial Inventory
            // unit_size instead of defaulting to 0.
            $table->decimal('weight_per_unit', 10, 2)->nullable()->after('low_stock_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('weight_per_unit');
        });
    }
};
