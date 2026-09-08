<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_price_history', function (Blueprint $table) {
            // Whether total_price was typed in as the cost of one whole
            // CASE (package_size x units_per_case) rather than one
            // individual package -- the invoice/shelf-tag basis varies by
            // source, so this can no longer be assumed universally.
            // Defaults false so every existing row (and every entry point
            // that doesn't yet offer the package/case toggle) keeps
            // today's behavior: qty is seeded/refreshed as one package.
            $table->boolean('priced_as_case')->default(false)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('costing_price_history', function (Blueprint $table) {
            $table->dropColumn('priced_as_case');
        });
    }
};
