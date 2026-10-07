<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            // Made in-house (e.g. apple butter) from other ingredients --
            // see costing_ingredient_components. Its price is computed from
            // those, never logged.
            $table->boolean('is_house_made')->default(false)->after('byproduct_name');
            // Grams one prep batch of the components makes, weighed after
            // cooking.
            $table->decimal('yield_g', 12, 3)->nullable()->after('is_house_made');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn(['is_house_made', 'yield_g']);
        });
    }
};
