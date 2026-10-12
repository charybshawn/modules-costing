<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_production_run_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_run_id')->constrained('costing_production_runs')->cascadeOnDelete();
            $table->foreignId('recipe_size_id')->constrained('costing_recipe_sizes')->cascadeOnDelete();
            // Planned units of this size filled from the run's mix.
            $table->unsignedInteger('units')->default(0);
            // Null until the run is completed; what was actually made.
            $table->unsignedInteger('actual_units')->nullable();
            $table->timestamps();

            $table->unique(['production_run_id', 'recipe_size_id'], 'costing_run_alloc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_production_run_allocations');
    }
};
