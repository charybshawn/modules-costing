<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            // A house-made ingredient's finished weight as a percentage of
            // the weighed ingredients that go into it (e.g. applesauce
            // cooks down to ~52%) -- holds at any batch size, unlike a fixed
            // gram yield. Can exceed 100 if unlisted water goes in.
            $table->decimal('cook_down_percent', 6, 2)->nullable()->after('is_house_made');
        });

        // Carry each existing gram yield over as the percentage of its
        // weighed components that it represents.
        $inputs = DB::table('costing_ingredient_components')
            ->join('costing_ingredients as component', 'component.id', '=', 'costing_ingredient_components.component_ingredient_id')
            ->where('component.unit_type', 'g')
            ->groupBy('costing_ingredient_components.ingredient_id')
            ->selectRaw('costing_ingredient_components.ingredient_id, SUM(costing_ingredient_components.quantity) as grams')
            ->pluck('grams', 'ingredient_id');

        DB::table('costing_ingredients')->whereNotNull('yield_g')->get(['id', 'yield_g'])
            ->each(function ($ingredient) use ($inputs) {
                $grams = (float) ($inputs[$ingredient->id] ?? 0);
                if ($grams > 0) {
                    DB::table('costing_ingredients')->where('id', $ingredient->id)
                        ->update(['cook_down_percent' => round((float) $ingredient->yield_g / $grams * 100, 2)]);
                }
            });

        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('yield_g');
        });
    }

    public function down(): void
    {
        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->decimal('yield_g', 12, 3)->nullable()->after('is_house_made');
        });

        $inputs = DB::table('costing_ingredient_components')
            ->join('costing_ingredients as component', 'component.id', '=', 'costing_ingredient_components.component_ingredient_id')
            ->where('component.unit_type', 'g')
            ->groupBy('costing_ingredient_components.ingredient_id')
            ->selectRaw('costing_ingredient_components.ingredient_id, SUM(costing_ingredient_components.quantity) as grams')
            ->pluck('grams', 'ingredient_id');

        DB::table('costing_ingredients')->whereNotNull('cook_down_percent')->get(['id', 'cook_down_percent'])
            ->each(function ($ingredient) use ($inputs) {
                DB::table('costing_ingredients')->where('id', $ingredient->id)
                    ->update(['yield_g' => round((float) ($inputs[$ingredient->id] ?? 0) * (float) $ingredient->cook_down_percent / 100, 3)]);
            });

        Schema::table('costing_ingredients', function (Blueprint $table) {
            $table->dropColumn('cook_down_percent');
        });
    }
};
