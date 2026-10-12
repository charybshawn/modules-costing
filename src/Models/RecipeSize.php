<?php

namespace Cultpantry\Costing\Models;

use Cultpantry\Costing\Contracts\FinishedGood;
use Cultpantry\Costing\Contracts\FinishedGoodRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One fill size of a recipe (e.g. the 280g and the 140g jar of the same
 * flavour). Owns its own finished product, whose price is the sell price,
 * and its own packaging lines.
 *
 * @property int $id
 * @property int $recipe_id
 * @property string $label
 * @property float $fill_size_g
 * @property int|null $finished_good_id
 * @property int $sort_order
 */
class RecipeSize extends Model
{
    protected $table = 'costing_recipe_sizes';

    protected $fillable = [
        'recipe_id',
        'label',
        'fill_size_g',
        'finished_good_id',
        'sort_order',
    ];

    protected $casts = [
        'fill_size_g' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function packaging(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'costing_recipe_size_packaging', 'recipe_size_id', 'ingredient_id')
            ->withPivot('quantity_per_unit')
            ->withTimestamps();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProductionRunAllocation::class);
    }

    public function finishedGood(): ?FinishedGood
    {
        return $this->finished_good_id !== null
            ? app(FinishedGoodRepository::class)->find($this->finished_good_id)
            : null;
    }

    /** The linked product's current price, or null with no product / no price. */
    public function sellPrice(): ?float
    {
        return $this->finishedGood()?->getPrice();
    }
}
