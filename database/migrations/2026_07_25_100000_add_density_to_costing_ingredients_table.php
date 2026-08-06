<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->decimal('density_g_per_ml', 8, 4)->nullable()->after('weight_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('density_g_per_ml');
        });
    }
};
