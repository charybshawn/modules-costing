<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            // e.g. "Juice", "Brine", "Fat". Presence = the ingredient has a
            // usable byproduct. Free (no separate cost/purchase) and always
            // assumed sufficient -- no inventory/stock tracking for it.
            $table->string('byproduct_name')->nullable()->after('weight_per_unit');
        });

        Schema::table('costing_ingredient_recipe', function (Blueprint $table) {
            $table->boolean('is_byproduct')->default(false)->after('quantity_per_jar');
            $table->dropUnique(['recipe_id', 'ingredient_id']);
            $table->unique(['recipe_id', 'ingredient_id', 'is_byproduct']);
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredient_recipe', function (Blueprint $table) {
            $table->dropUnique(['recipe_id', 'ingredient_id', 'is_byproduct']);
            $table->dropColumn('is_byproduct');
            $table->unique(['recipe_id', 'ingredient_id']);
        });

        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('byproduct_name');
        });
    }
};
