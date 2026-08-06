<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            // Same unit as the ingredient's on-hand quantity (grams or
            // units, per unit_type). Nullable -- a threshold is opt-in per
            // ingredient; leaving it blank disables low-stock alerts for
            // that ingredient.
            $table->decimal('low_stock_threshold', 10, 2)->nullable()->after('preferred_source');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
