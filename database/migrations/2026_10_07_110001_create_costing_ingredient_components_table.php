<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_ingredient_components', function (Blueprint $table) {
            $table->id();
            // The house-made ingredient...
            $table->foreignId('ingredient_id')->constrained('costing_ingredients')->cascadeOnDelete();
            // ...and one of the ingredients it's made from. Restricted: an
            // ingredient can't be deleted while a house-made one uses it.
            $table->foreignId('component_ingredient_id')->constrained('costing_ingredients')->restrictOnDelete();
            // Grams (or units) per prep batch.
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['ingredient_id', 'component_ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_ingredient_components');
    }
};
