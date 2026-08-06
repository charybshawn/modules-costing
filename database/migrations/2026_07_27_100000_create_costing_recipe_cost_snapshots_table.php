<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_recipe_cost_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('costing_recipes')->cascadeOnDelete();
            $table->foreignId('production_run_id')->constrained('costing_production_runs')->cascadeOnDelete();
            $table->unsignedInteger('jars_produced');

            // Frozen inputs -- these live on Recipe and can be edited later,
            // so they're copied here so an old snapshot stays interpretable
            // even after the recipe's current settings have moved on.
            $table->decimal('sell_price', 8, 2)->nullable();
            $table->decimal('fill_size_g', 10, 2)->nullable();
            $table->decimal('cost_buffer_percent', 5, 2)->nullable();

            // Computed outputs as flat columns (not JSON) so they're
            // directly queryable/chartable without parsing.
            $table->decimal('raw_cost', 10, 4);
            $table->decimal('yield_grams', 10, 2);
            $table->decimal('buffered_cost', 10, 4);
            $table->decimal('actual_cost_per_jar', 10, 4);
            $table->decimal('food_cost_percent', 5, 2)->nullable();

            $table->boolean('any_stale')->default(false);
            $table->boolean('any_missing')->default(false);

            // Per-ingredient detail (qty, price used, cost contribution) --
            // audit/drill-down detail, not needed for the chart itself.
            $table->json('ingredient_breakdown');

            $table->timestamps();

            $table->index(['recipe_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_recipe_cost_snapshots');
    }
};
