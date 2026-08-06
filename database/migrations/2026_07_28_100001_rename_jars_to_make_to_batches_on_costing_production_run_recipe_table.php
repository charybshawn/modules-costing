<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_production_run_recipe', function (Blueprint $table) {
            $table->renameColumn('jars_to_make', 'batches');
        });
    }

    public function down(): void
    {
        Schema::table('costing_production_run_recipe', function (Blueprint $table) {
            $table->renameColumn('batches', 'jars_to_make');
        });
    }
};
