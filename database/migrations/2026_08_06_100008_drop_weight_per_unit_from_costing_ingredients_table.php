<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Was the ingredient-level fallback for the Units-to-Buy calculation
     * when no source had a package size set. Now that Sources are managed
     * inline (provider/brand/package size, editable straight from the
     * Ingredient page), every real source is expected to carry its own
     * package size directly -- a second, ingredient-level "typical size"
     * answering the same question was itself a source of confusion, and no
     * ingredient currently has it set (confirmed before writing this
     * migration). CalculateIngredientCosting::resolvePackageSize() no
     * longer falls back to it.
     */
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('weight_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->decimal('weight_per_unit', 10, 2)->nullable()->after('low_stock_threshold');
        });
    }
};
