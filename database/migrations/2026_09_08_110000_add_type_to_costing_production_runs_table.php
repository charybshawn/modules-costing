<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_production_runs', function (Blueprint $table) {
            $table->string('type')->default('production')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('costing_production_runs', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
