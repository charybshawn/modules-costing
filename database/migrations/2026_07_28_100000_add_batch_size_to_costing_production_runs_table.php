<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_production_runs', function (Blueprint $table) {
            // Default 20 for new rows (the user's stated real-world default
            // batch yield). Existing rows are backfilled below to 1 -- a
            // no-op multiplier -- so their pivot batches value (the old
            // jars_to_make number, carried over unchanged by the sibling
            // migration) keeps meaning the same real unit count it always
            // did: 1 x (old jars_to_make) = the same total.
            $table->unsignedInteger('batch_size')->default(20)->after('name');
        });

        DB::table('costing_production_runs')->update(['batch_size' => 1]);
    }

    public function down(): void
    {
        Schema::table('costing_production_runs', function (Blueprint $table) {
            $table->dropColumn('batch_size');
        });
    }
};
