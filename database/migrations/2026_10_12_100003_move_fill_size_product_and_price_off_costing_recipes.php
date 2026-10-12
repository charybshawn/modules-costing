<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fill size and finished product now belong to a recipe's sizes
 * (costing_recipe_sizes), and the sell price is read from the linked
 * product, so none of the three live on the recipe any more. Pre-production:
 * reseed with `migrate:fresh --module costing` rather than backfilling.
 */
return new class extends Migration
{
    public function up(): void
    {
        // product_id's foreign key was already dropped by
        // drop_product_id_foreign_key_from_costing_recipes_table.
        Schema::table('costing_recipes', function (Blueprint $table) {
            $table->dropColumn(['product_id', 'sell_price', 'fill_size_g']);
        });

        Schema::table('costing_production_run_recipe', function (Blueprint $table) {
            $table->dropColumn('actual_units');
        });

        Schema::table('costing_recipe_cost_snapshots', function (Blueprint $table) {
            // A snapshot is per size now. Nullable + nullOnDelete: it is a
            // frozen record and outlives the size it was taken for.
            $table->foreignId('recipe_size_id')->nullable()->after('recipe_id')
                ->constrained('costing_recipe_sizes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costing_recipe_cost_snapshots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipe_size_id');
        });

        Schema::table('costing_production_run_recipe', function (Blueprint $table) {
            $table->unsignedInteger('actual_units')->nullable()->after('batches');
        });

        Schema::table('costing_recipes', function (Blueprint $table) {
            $table->decimal('sell_price', 8, 2)->nullable()->after('notes');
            $table->decimal('fill_size_g', 10, 2)->nullable()->after('sell_price');
            $table->unsignedBigInteger('product_id')->nullable()->after('id');
        });
    }
};
