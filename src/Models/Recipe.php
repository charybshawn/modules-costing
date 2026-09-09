<?php

namespace Cultpantry\Costing\Models;

use Cultpantry\Costing\Contracts\FinishedGood;
use Cultpantry\Costing\Contracts\FinishedGoodRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A "recipe" is a flavour (e.g. "Sriracha Maple Bacon") -- one row per
 * flavour, with ingredient quantities-per-jar attached via the pivot.
 *
 * Optionally linked to a host-app finished good (e.g. a storefront product)
 * via the nullable `product_id` column -- most recipes won't have one, but
 * when set, completing a production run for this recipe credits that many
 * units to the linked finished good's stock (see CompleteProductionRun /
 * UncompleteProductionRun), on top of the existing cost-snapshot/ingredient
 * bookkeeping. The link is what turns a production run from a pure
 * costing/inventory-deduction exercise into one that also stocks the shelf.
 * The host app owns what "product_id" actually points to -- resolved
 * through Cultpantry\Costing\Contracts\FinishedGoodRepository, never a
 * direct Eloquent relation into a host model, so this package has no
 * dependency on any specific host app's schema.
 *
 * @property int $id
 * @property int|null $product_id
 * @property int|null $min_stock_threshold
 * @property bool $is_active
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
        'min_stock_threshold',
        'is_active',
        'name',
        'notes',
        'sell_price',
        'fill_size_g',
        'cost_buffer_percent',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sell_price' => 'decimal:2',
        'fill_size_g' => 'decimal:2',
        'cost_buffer_percent' => 'decimal:2',
    ];

    public function finishedGood(): ?FinishedGood
    {
        return $this->product_id !== null
            ? app(FinishedGoodRepository::class)->find($this->product_id)
            : null;
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
