<?php

namespace Cultpantry\Costing\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A "recipe" is a flavour (e.g. "Sriracha Maple Bacon") -- one row per
 * flavour, with ingredient quantities-per-jar attached via the pivot.
 *
 * Optionally linked to App\Models\Product via the nullable `product_id`
 * FK (`->nullOnDelete()`) -- most recipes won't have one, but when set,
 * completing a production run for this recipe credits that many units to
 * the linked product's storefront stock (see CompleteProductionRun /
 * UncompleteProductionRun), on top of the existing cost-snapshot/ingredient
 * bookkeeping. The link is what turns a production run from a pure
 * costing/inventory-deduction exercise into one that also stocks the shelf.
 *
 * @property int $id
 * @property int|null $product_id
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
        'product_id',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
