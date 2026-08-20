<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_ingredient_package_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('costing_ingredients')->cascadeOnDelete();
            $table->string('provider');
            $table->string('brand')->nullable();
            $table->decimal('package_size', 12, 2);
            $table->timestamps();

            // Explicit name: Laravel's auto-generated one
            // ("costing_ingredient_package_sizes_ingredient_id_provider_brand_index",
            // 67 chars) exceeds MySQL's 64-character identifier limit
            // (error 1059).
            $table->index(['ingredient_id', 'provider', 'brand'], 'costing_ingredient_package_sizes_ipb_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_ingredient_package_sizes');
    }
};
