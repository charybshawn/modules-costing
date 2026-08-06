<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_ingredient_recipe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('costing_recipes')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('costing_ingredients')->cascadeOnDelete();
            // Grams per jar (or units per jar, for packaging ingredients).
            $table->decimal('quantity_per_jar', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_ingredient_recipe');
    }
};
