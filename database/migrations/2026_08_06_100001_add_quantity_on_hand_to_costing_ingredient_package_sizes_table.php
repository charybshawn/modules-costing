<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredient_package_sizes', function (Blueprint $table) {
            // This table now does double duty: package *size* (existing,
            // used by CalculateIngredientCosting's purchase-size fallback)
            // and current *quantity* on hand for that same provider/brand
            // (new) -- the same row is the natural home for both, since
            // they're facts about the same real-world source.
            $table->decimal('quantity_on_hand', 12, 2)->default(0)->after('package_size');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredient_package_sizes', function (Blueprint $table) {
            $table->dropColumn('quantity_on_hand');
        });
    }
};
