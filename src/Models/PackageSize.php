<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A known real source (provider/brand combination) for an ingredient --
 * e.g. Cream Cheese from GFS/Kraft comes in 20kg blocks, but from
 * Wholesale Club/Brand X it's 15kg. Does double duty: package *size* (lets
 * CalculateIngredientCosting round shopping-list shortfalls to the real
 * package size for whichever provider/brand actually won this week's
 * price) and current *quantity on hand* for that same source (every
 * inventory adjustment is tied to one of these rows -- see
 * InventoryAdjustment). Every ingredient always has one "Unspecified" row
 * (provider: 'Unspecified', brand: null, package_size: 1) auto-created in
 * Ingredient::booted(), so there's always something to pick even when the
 * real source genuinely isn't known -- its fixed package_size of 1 means
 * its quantity field is just raw grams/units directly.
 *
 * @property int $id
 * @property int $ingredient_id
 * @property string $provider
 * @property string|null $brand
 * @property float $package_size
 * @property float $quantity_on_hand
 */
class PackageSize extends Model
{
    protected $table = 'costing_ingredient_package_sizes';

    protected $fillable = [
        'ingredient_id',
        'provider',
        'brand',
        'package_size',
        'quantity_on_hand',
    ];

    protected $casts = [
        'package_size' => 'decimal:2',
        'quantity_on_hand' => 'decimal:2',
    ];

    protected $appends = ['packages'];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    /**
     * How many whole/partial packages quantity_on_hand represents -- the
     * display-facing inverse of "packages x size = quantity" data entry.
     */
    public function getPackagesAttribute(): float
    {
        $size = (float) $this->package_size;

        return $size > 0 ? round((float) $this->quantity_on_hand / $size, 3) : 0.0;
    }
}
