<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->nullable();
            // 'g' (gram-based, e.g. cream cheese) or 'unit' (packaging, e.g. deli cups).
            // Determines whether price/quantity math scales by 1000 (per kg) or not.
            $table->string('unit_type')->default('g');
            $table->decimal('waste_percent', 5, 2)->default(100);
            $table->string('preferred_source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_ingredients');
    }
};
