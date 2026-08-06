<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->string('preferred_brand')->nullable()->after('preferred_source');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('preferred_brand');
        });
    }
};
