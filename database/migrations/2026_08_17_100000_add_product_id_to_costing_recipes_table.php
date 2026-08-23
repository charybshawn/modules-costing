<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            // Nullable, most recipes won't have one initially -- links a
            // flavour to the storefront Product its finished units are
            // credited to when a production run completes (see
            // CompleteProductionRun). nullOnDelete rather than cascade: a
            // deleted product shouldn't take the recipe (and its cost
            // history) down with it, just unlink it.
            $table->foreignId('product_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
