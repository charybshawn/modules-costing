<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            // Every unit fills to ~280g -- new recipes start there. Stays
            // nullable: blank still means "cost the whole batch as one unit".
            $table->decimal('fill_size_g', 10, 2)->nullable()->default(280)->change();
        });
    }

    public function down(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            $table->decimal('fill_size_g', 10, 2)->nullable()->default(null)->change();
        });
    }
};
