<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_price_history', function (Blueprint $table) {
            $table->foreignId('package_size_id')->nullable()->after('ingredient_id')->constrained('costing_ingredient_package_sizes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costing_price_history', function (Blueprint $table) {
            $table->dropForeign(['package_size_id']);
            $table->dropColumn('package_size_id');
        });
    }
};
