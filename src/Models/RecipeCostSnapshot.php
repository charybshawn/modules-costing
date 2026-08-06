<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen record of a recipe's cost, taken automatically when a
 * Production Run is completed -- the moment cost stops being theoretical
 * and reflects what was actually made. Every computed figure is captured
 * as-is at that moment, since CalculateRecipeCost recomputes live from
 * current ingredient prices and would otherwise make old numbers
 * unrecoverable once prices change.
 *
 * @property int $id
 * @property int $recipe_id
 * @property int $production_run_id
 * @property int $jars_produced
 * @property float|null $sell_price
 * @property float|null $fill_size_g
 * @property float|null $cost_buffer_percent
 * @property float $raw_cost
 * @property float $yield_grams
 * @property float $buffered_cost
 * @property float $actual_cost_per_jar
 * @property float|null $food_cost_percent
 * @property bool $any_stale
 * @property bool $any_missing
 * @property array $ingredient_breakdown
 */
class RecipeCostSnapshot extends Model
{
    protected $table = 'costing_recipe_cost_snapshots';

    protected $fillable = [
        'recipe_id',
        'production_run_id',
        'jars_produced',
        'sell_price',
        'fill_size_g',
        'cost_buffer_percent',
        'raw_cost',
        'yield_grams',
        'buffered_cost',
        'actual_cost_per_jar',
        'food_cost_percent',
        'any_stale',
        'any_missing',
        'ingredient_breakdown',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'fill_size_g' => 'decimal:2',
        'cost_buffer_percent' => 'decimal:2',
        'raw_cost' => 'decimal:4',
        'yield_grams' => 'decimal:2',
        'buffered_cost' => 'decimal:4',
        'actual_cost_per_jar' => 'decimal:4',
        'food_cost_percent' => 'decimal:2',
        'any_stale' => 'boolean',
        'any_missing' => 'boolean',
        'ingredient_breakdown' => 'array',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function productionRun(): BelongsTo
    {
        return $this->belongsTo(ProductionRun::class);
    }
}
