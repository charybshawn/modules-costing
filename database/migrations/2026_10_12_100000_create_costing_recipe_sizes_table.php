<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_recipe_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('costing_recipes')->cascadeOnDelete();
            $table->string('label');
            // Grams of product in one filled unit of this size.
            $table->decimal('fill_size_g', 10, 2);
            // The host app's finished good (e.g. a storefront product) this
            // size's units are credited to on completion, and where its sell
            // price comes from. Deliberately not a foreign key: the package
            // resolves it through FinishedGoodRepository, so a host without a
            // `products` table can run this migration (see
            // drop_product_id_foreign_key_from_costing_recipes_table). A
            // deleted product simply stops resolving.
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['recipe_id', 'fill_size_g'], 'costing_rsizes_recipe_fill_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_recipe_sizes');
    }
};
