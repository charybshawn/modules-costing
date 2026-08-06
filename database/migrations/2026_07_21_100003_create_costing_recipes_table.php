<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_recipes', function (Blueprint $table) {
            $table->id();
            // A "recipe" is a flavour, e.g. "Sriracha Maple Bacon".
            $table->string('name')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_recipes');
    }
};
