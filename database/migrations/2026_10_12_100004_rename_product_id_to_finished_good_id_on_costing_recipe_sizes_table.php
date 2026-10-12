<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A size links to whatever the host sells that size as -- a variation of a
 * storefront product, not the product itself -- so the column is named for
 * the contract (FinishedGood), not for one host's model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_recipe_sizes', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });

        Schema::table('costing_recipe_sizes', function (Blueprint $table) {
            $table->renameColumn('product_id', 'finished_good_id');
        });

        Schema::table('costing_recipe_sizes', function (Blueprint $table) {
            $table->index('finished_good_id', 'costing_rsizes_fg_idx');
        });
    }

    public function down(): void
    {
        Schema::table('costing_recipe_sizes', function (Blueprint $table) {
            $table->dropIndex('costing_rsizes_fg_idx');
        });

        Schema::table('costing_recipe_sizes', function (Blueprint $table) {
            $table->renameColumn('finished_good_id', 'product_id');
        });

        Schema::table('costing_recipe_sizes', function (Blueprint $table) {
            $table->index('product_id');
        });
    }
};
