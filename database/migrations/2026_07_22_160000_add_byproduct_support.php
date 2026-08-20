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
        });

        // MySQL/InnoDB refuses to drop an index that's still backing a
        // foreign key constraint (error 1553) -- recipe_id's and
        // ingredient_id's FKs were both relying on the old composite
        // unique index as their supporting index, since neither column
        // had a single-column index of its own. SQLite has no such
        // restriction, which is why this passed locally and only failed
        // against a real MySQL database. Drop both FKs first, swap the
        // unique index, then recreate the FKs -- MySQL creates whatever
        // supporting index each one needs from what's left afterward.
        Schema::table('costing_ingredient_recipe', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropForeign(['ingredient_id']);
            $table->dropUnique(['recipe_id', 'ingredient_id']);
        });

        Schema::table('costing_ingredient_recipe', function (Blueprint $table) {
            $table->unique(['recipe_id', 'ingredient_id', 'is_byproduct']);
            $table->foreign('recipe_id')->references('id')->on('costing_recipes')->cascadeOnDelete();
            $table->foreign('ingredient_id')->references('id')->on('costing_ingredients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredient_recipe', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropForeign(['ingredient_id']);
            $table->dropUnique(['recipe_id', 'ingredient_id', 'is_byproduct']);
        });

        Schema::table('costing_ingredient_recipe', function (Blueprint $table) {
            $table->unique(['recipe_id', 'ingredient_id']);
            $table->foreign('recipe_id')->references('id')->on('costing_recipes')->cascadeOnDelete();
            $table->foreign('ingredient_id')->references('id')->on('costing_ingredients')->cascadeOnDelete();
            $table->dropColumn('is_byproduct');
        });

        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('byproduct_name');
        });
    }
};
