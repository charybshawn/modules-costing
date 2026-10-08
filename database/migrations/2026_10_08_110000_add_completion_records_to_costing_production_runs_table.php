<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_production_runs', function (Blueprint $table) {
            // The purchase order and recipe sheets exactly as they stood when
            // the run was completed (just before its stock was deducted) --
            // a completed run shows these as its record instead of
            // recalculating against today's stock and recipes. Cleared again
            // by Undo Completion.
            $table->json('purchase_order_record')->nullable()->after('completed_at');
            $table->json('recipe_sheet_record')->nullable()->after('purchase_order_record');
        });
    }

    public function down(): void
    {
        Schema::table('costing_production_runs', function (Blueprint $table) {
            $table->dropColumn(['purchase_order_record', 'recipe_sheet_record']);
        });
    }
};
