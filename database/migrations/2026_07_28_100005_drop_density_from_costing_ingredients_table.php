<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The $/100mL figure this fed (Ingredient::pricePer100ml()) turned out
     * to be a misunderstanding of what was actually wanted -- a simple
     * $/100g comparison, with weight and volume assumed 1:1, not a real
     * density-adjusted mL conversion. No ingredient ever had this field
     * set (confirmed before writing this migration), so there's no data to
     * preserve.
     */
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('density_g_per_ml');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->decimal('density_g_per_ml', 8, 4)->nullable()->after('weight_per_unit');
        });
    }
};
