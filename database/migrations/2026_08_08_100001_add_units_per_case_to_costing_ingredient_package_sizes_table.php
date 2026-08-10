<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredient_package_sizes', function (Blueprint $table) {
            // Purchasing constraint only, independent of package_size's
            // meaning ("size of one individual package"): the multiple you
            // must order in for this source, e.g. GFS beef stock
            // concentrate is 946g cans, only orderable in cases of 4.
            // Recount/adjust granularity is unaffected -- always individual
            // packages -- so this never appears in that math, only in
            // CalculateProductionPlan's shopping-list rounding.
            $table->unsignedInteger('units_per_case')->default(1)->after('package_size');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredient_package_sizes', function (Blueprint $table) {
            $table->dropColumn('units_per_case');
        });
    }
};
