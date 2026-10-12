<?php

/*
 * This module's tests. They run inside the host app (it provides the User
 * model, the Event log, the admin layout and the database), which loads this
 * file from vendor/cultpantry/costing/tests -- see the host's tests/Pest.php
 * and phpunit.xml. Run them from the host: `php artisan test --testsuite=Modules`.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__);

use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Models\RecipeSize;

/** A size on the recipe (created once per fill weight), optionally linked to a product and packed with the given ingredient quantities. */
function recipeSize(Recipe $recipe, float $fillG = 280, array $packaging = [], ?int $productId = null): RecipeSize
{
    $size = RecipeSize::firstOrCreate(
        ['recipe_id' => $recipe->id, 'fill_size_g' => $fillG],
        ['label' => rtrim(rtrim(number_format($fillG, 2, '.', ''), '0'), '.').'g', 'finished_good_id' => $productId],
    );

    if ($packaging !== []) {
        $size->packaging()->sync(collect($packaging)->map(fn ($qty) => ['quantity_per_unit' => $qty])->all());
    }

    return $size;
}

/** Allocates `$units` of a size (created at `$fillG` if needed) on the run. */
function allocateFill(ProductionRun $run, Recipe $recipe, int $units, float $fillG = 280, array $packaging = [], ?int $productId = null): RecipeSize
{
    $size = recipeSize($recipe, $fillG, $packaging, $productId);
    $run->allocations()->updateOrCreate(['recipe_size_id' => $size->id], ['units' => $units]);

    return $size;
}
