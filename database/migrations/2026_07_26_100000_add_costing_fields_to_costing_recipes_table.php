<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            $table->decimal('sell_price', 8, 2)->nullable()->after('notes');
            // Actual measured fill weight per jar, in grams -- distinct from
            // the theoretical sum of ingredient weights, since real
            // production loses some product between mixing and filling.
            $table->decimal('fill_size_g', 10, 2)->nullable()->after('sell_price');
            // Optional contingency % added on top of raw ingredient cost
            // (e.g. for price drift or over-portioning). Blank/0 = none.
            $table->decimal('cost_buffer_percent', 5, 2)->nullable()->after('fill_size_g');
        });
    }

    public function down(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            $table->dropColumn(['sell_price', 'fill_size_g', 'cost_buffer_percent']);
        });
    }
};
