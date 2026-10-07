<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            // Units this flavour is usually made in at a time (e.g. 20) --
            // the recipe page's batch calculator starts from it. A
            // production run still sets its own batch size.
            $table->unsignedInteger('preferred_batch_size')->nullable()->after('fill_size_g');
        });
    }

    public function down(): void
    {
        Schema::table('costing_recipes', function (Blueprint $table) {
            $table->dropColumn('preferred_batch_size');
        });
    }
};
