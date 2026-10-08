<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Named explicitly: the default name for this pair is 74 characters, over
    // MySQL's 64-character identifier limit.
    private const UNIQUE = 'costing_ingredient_components_pair_unique';

    public function up(): void
    {
        if (! Schema::hasTable('costing_ingredient_components')) {
            Schema::create('costing_ingredient_components', function (Blueprint $table) {
                $table->id();
                // The house-made ingredient...
                $table->foreignId('ingredient_id')->constrained('costing_ingredients')->cascadeOnDelete();
                // ...and one of the ingredients it's made from. Restricted: an
                // ingredient can't be deleted while a house-made one uses it.
                $table->foreignId('component_ingredient_id')->constrained('costing_ingredients')->restrictOnDelete();
                // Grams (or units) per prep batch.
                $table->decimal('quantity', 12, 3)->default(0);
                $table->timestamps();

                $table->unique(['ingredient_id', 'component_ingredient_id'], self::UNIQUE);
            });

            return;
        }

        // An earlier version of this migration failed on MySQL at the
        // too-long unique index name after the table itself was created
        // (MySQL can't roll back DDL), so it was never recorded as run.
        // Finish that table instead of creating it again.
        $indexes = collect(Schema::getIndexes('costing_ingredient_components'))->pluck('name');
        $foreignColumns = collect(Schema::getForeignKeys('costing_ingredient_components'))->pluck('columns')->flatten();

        Schema::table('costing_ingredient_components', function (Blueprint $table) use ($indexes, $foreignColumns) {
            if (! $foreignColumns->contains('ingredient_id')) {
                $table->foreign('ingredient_id')->references('id')->on('costing_ingredients')->cascadeOnDelete();
            }
            if (! $foreignColumns->contains('component_ingredient_id')) {
                $table->foreign('component_ingredient_id')->references('id')->on('costing_ingredients')->restrictOnDelete();
            }
            if (! $indexes->contains(self::UNIQUE)) {
                $table->unique(['ingredient_id', 'component_ingredient_id'], self::UNIQUE);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_ingredient_components');
    }
};
