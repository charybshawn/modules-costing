<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A "recipe" is a flavour (e.g. "Sriracha Maple Bacon") -- one row per
 * flavour, with ingredient quantities-per-jar attached via the pivot.
 *
 * Deliberately not linked to App\Models\Product yet -- costing/recipes and
 * the storefront catalogue are kept independent for now, by request. The
 * intended extension point for a future cost-vs-price/margin view is a
 * single additive migration adding a nullable `product_id` FK on
 * costing_recipes (`->nullOnDelete()`) plus a `belongsTo(Product::class)`
 * here. No schema change needed until that's actually wanted.
 *
 * @property int $id
 * @property string $name
 * @property string|null $notes
 * @property float|null $sell_price
 * @property float|null $fill_size_g
 * @property float|null $cost_buffer_percent
 */
class Recipe extends Model
{
    protected $table = 'costing_recipes';

    protected $fillable = [
        'name',
        'notes',
        'sell_price',
        'fill_size_g',
        'cost_buffer_percent',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'fill_size_g' => 'decimal:2',
        'cost_buffer_percent' => 'decimal:2',
    ];

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
