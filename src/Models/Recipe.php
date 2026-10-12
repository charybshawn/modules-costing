<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "recipe" is a flavour (e.g. "Sriracha Maple Bacon") -- one row per
 * flavour, with ingredient quantities-per-jar attached via the pivot.
 *
 * What it is filled into lives on its sizes (RecipeSize): each size has a
 * fill weight, its own packaging and an optional host-app finished good
 * (e.g. a storefront product). Completing a production run credits the units
 * allocated to each size to that size's finished good (see
 * CompleteProductionRun / UncompleteProductionRun). The host app owns what a
 * size's finished_good_id points to -- resolved through
 * Cultpantry\Costing\Contracts\FinishedGoodRepository, never a direct
 * Eloquent relation into a host model.
 *
 * @property int $id
 * @property int|null $min_stock_threshold
 * @property bool $is_active
 * @property string $name
 * @property string|null $notes
 * @property int|null $preferred_batch_size
 * @property float|null $cost_buffer_percent
 */
class Recipe extends Model
{
    /** Grams in the first size a new recipe gets. */
    public const DEFAULT_FILL_SIZE_G = 280;

    protected $table = 'costing_recipes';

    protected $fillable = [
        'min_stock_threshold',
        'is_active',
        'name',
        'notes',
        'preferred_batch_size',
        'cost_buffer_percent',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'preferred_batch_size' => 'integer',
        'cost_buffer_percent' => 'decimal:2',
    ];

    /**
     * Grams of product the recipe's amounts make (gram-based, non-byproduct
     * lines; packaging and counted items aren't mass). Needs mainIngredients
     * loaded or loads it.
     */
    public function yieldGrams(): float
    {
        $this->loadMissing('mainIngredients');

        return (float) $this->mainIngredients
            ->filter(fn (Ingredient $ingredient) => $ingredient->isGramBased())
            ->sum(fn (Ingredient $ingredient) => (float) $ingredient->pivot->quantity_per_jar);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(RecipeSize::class)->orderBy('sort_order')->orderBy('fill_size_g');
    }

    /**
     * Recipes a user can still actively choose to produce -- excludes
     * discontinued flavours from pickers like the Production Planner's
     * "add recipe to this run" list, mirroring App\Models\Product's own
     * is_active/scopeActive() convention in the host app.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function productionRuns(): BelongsToMany
    {
        return $this->belongsToMany(ProductionRun::class, 'costing_production_run_recipe', 'recipe_id', 'production_run_id');
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'costing_ingredient_recipe', 'recipe_id', 'ingredient_id')
            ->withPivot('quantity_per_jar', 'is_byproduct')
            ->withTimestamps();
    }

    /**
     * Solid/main ingredient lines only -- what CalculateProductionPlan
     * costs and shops for. withPivotValue() both scopes reads (WHERE
     * is_byproduct = false) and auto-sets that value on sync()/attach(),
     * so callers never have to include it in the sync data themselves.
     */
    public function mainIngredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'costing_ingredient_recipe', 'recipe_id', 'ingredient_id')
            ->withPivot('quantity_per_jar')
            ->withPivotValue('is_byproduct', false)
            ->withTimestamps();
    }

    /**
     * Byproduct lines (e.g. pickle juice) -- free, always assumed
     * sufficient, no cost/inventory tracking, so excluded from the
     * shopping-list calculation entirely. Purely recipe documentation.
     */
    public function byproductIngredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'costing_ingredient_recipe', 'recipe_id', 'ingredient_id')
            ->withPivot('quantity_per_jar')
            ->withPivotValue('is_byproduct', true)
            ->withTimestamps();
    }
}
