<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            // Whole jars, same unit CalculateMaxProducibleUnits returns.
            // Nullable -- opt-in per recipe, same "blank disables it"
            // convention the old Ingredient::low_stock_threshold used.
            $table->unsignedInteger('min_stock_threshold')->nullable()->after('product_id');
            $table->boolean('is_active')->default(true)->after('min_stock_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            $table->dropColumn(['min_stock_threshold', 'is_active']);
        });
    }
};
