<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_production_run_recipe', function (Blueprint $table) {
            // Null until the run is completed -- planned output is
            // batch_size x batches; this records what was actually made,
            // which can differ (e.g. batches running heavier than the
            // assumed yield) without any change in ingredients consumed.
            $table->unsignedInteger('actual_units')->nullable()->after('batches');
        });
    }

    public function down(): void
    {
        Schema::table('costing_production_run_recipe', function (Blueprint $table) {
            $table->dropColumn('actual_units');
        });
    }
};
