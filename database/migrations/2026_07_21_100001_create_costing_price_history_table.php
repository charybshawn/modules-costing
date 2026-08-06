<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costing_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('costing_ingredients')->cascadeOnDelete();
            // Nullable on purpose: rows logged without a checked date/qty (incomplete
            // entries) simply never qualify for the "last 7 days" pricing window
            // instead of throwing a divide-by-zero the way the old spreadsheet did.
            $table->date('purchased_at')->nullable();
            $table->string('provider');
            $table->decimal('qty', 12, 2)->nullable();
            $table->decimal('total_price', 10, 2)->nullable();
            $table->string('sku')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['ingredient_id', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_price_history');
    }
};
