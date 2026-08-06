<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_inventory', function (Blueprint $table) {
            // Null by default -- on_hand keeps computing as
            // unit_size x units_on_hand until a walk-in count (using known
            // per-brand package sizes) is entered, at which point the raw
            // counted total is stored here directly and takes over. Keeps
            // unit_size free to do its other job (purchase_size fallback in
            // CalculateIngredientCosting) without either meaning corrupting
            // the other, instead of forcing one number/one field to answer
            // two different questions.
            $table->decimal('counted_on_hand', 12, 2)->nullable()->after('units_on_hand');
        });
    }

    public function down(): void
    {
        Schema::table('costing_inventory', function (Blueprint $table) {
            $table->dropColumn('counted_on_hand');
        });
    }
};
