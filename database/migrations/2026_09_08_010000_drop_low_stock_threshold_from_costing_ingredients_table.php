<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            // Reordering now lives on the Recipe instead (see the
            // min_stock_threshold/is_active migration on costing_recipes) --
            // a raw per-ingredient gram/unit threshold couldn't meaningfully
            // account for one ingredient feeding several recipes at once.
            $table->dropColumn('low_stock_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->decimal('low_stock_threshold', 10, 2)->nullable()->after('preferred_source');
        });
    }
};
