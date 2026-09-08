<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            // Drops only the FK constraint added by the original
            // ->constrained()->nullOnDelete() call -- product_id itself
            // stays exactly as it is (same column, same nullable unsigned
            // bigint, same name). The package now resolves it through
            // Cultpantry\Costing\Contracts\FinishedGoodRepository instead
            // of a real Eloquent FK, so a host app without a `products`
            // table can run this migration too.
            $table->dropForeign(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }
};
