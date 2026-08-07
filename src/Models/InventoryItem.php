<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ingredient_id
 * @property string|null $notes
 */
class InventoryItem extends Model
{
    protected $table = 'costing_inventory';

    protected $fillable = [
        'ingredient_id',
        'notes',
    ];

    protected $appends = ['on_hand'];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function packageSizes(): HasMany
    {
        return $this->hasMany(PackageSize::class, 'ingredient_id', 'ingredient_id');
    }

    /**
     * On Hand (g) = sum of every known source's quantity_on_hand
     * (costing_ingredient_package_sizes, including the always-present
     * Unspecified fallback) -- replaces the old single aggregate column.
     * Every existing caller keeps reading $inventory->on_hand exactly as
     * before; only this computation changed.
     */
    public function getOnHandAttribute(): float
    {
        return (float) PackageSize::where('ingredient_id', $this->ingredient_id)->sum('quantity_on_hand');
    }
}
