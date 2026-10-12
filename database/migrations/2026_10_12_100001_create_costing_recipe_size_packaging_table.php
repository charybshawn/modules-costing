<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_recipe_size_packaging', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_size_id')->constrained('costing_recipe_sizes')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('costing_ingredients')->cascadeOnDelete();
            // Units of this packaging item used by one filled unit of the size.
            $table->decimal('quantity_per_unit', 10, 3);
            $table->timestamps();

            $table->unique(['recipe_size_id', 'ingredient_id'], 'costing_rsize_pack_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_recipe_size_packaging');
    }
};
