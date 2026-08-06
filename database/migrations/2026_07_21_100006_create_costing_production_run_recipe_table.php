<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_production_run_recipe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_run_id')->constrained('costing_production_runs')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('costing_recipes')->cascadeOnDelete();
            $table->unsignedInteger('jars_to_make')->default(0);
            $table->timestamps();

            $table->unique(['production_run_id', 'recipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_production_run_recipe');
    }
};
