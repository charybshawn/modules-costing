<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('costing_ingredients')->cascadeOnDelete();
            // Every adjustment is tied to a specific source (provider/brand)
            // row on costing_ingredient_package_sizes, including the
            // auto-created "Unspecified" fallback for genuinely unknown
            // sources -- there's no free-floating "adjust the ingredient
            // total" path. on_hand_before/after below are this row's
            // quantity, not the ingredient's aggregate.
            $table->foreignId('package_size_id')->constrained('costing_ingredient_package_sizes')->cascadeOnDelete();
            // Set only for reason=production_run -- links the automatic
            // deduction back to the run that caused it. nullOnDelete, not
            // cascade: deleting a production run shouldn't erase the fact
            // that inventory was actually consumed.
            $table->foreignId('production_run_id')->nullable()->constrained('costing_production_runs')->nullOnDelete();
            // Null for system-triggered rows (production_run) -- there's no
            // human decision to attribute those to.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->decimal('delta', 12, 2);
            $table->decimal('on_hand_before', 12, 2);
            $table->decimal('on_hand_after', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_inventory_adjustments');
    }
};
