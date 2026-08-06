<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A known real purchase package size for one provider/brand combination --
 * e.g. Cream Cheese from GFS/Kraft comes in 20kg blocks, but from
 * Wholesale Club/Brand X it's 15kg. Lets CalculateIngredientCosting round
 * shopping-list shortfalls to the real package size for whichever
 * provider/brand actually won this week's price, instead of one fixed
 * ingredient-level size (InventoryItem.unit_size) that can only ever be
 * right for one brand at a time.
 *
 * @property int $id
 * @property int $ingredient_id
 * @property string $provider
 * @property string|null $brand
 * @property float $package_size
 */
class PackageSize extends Model
{
    protected $table = 'costing_ingredient_package_sizes';

    protected $fillable = [
        'ingredient_id',
        'provider',
        'brand',
        'package_size',
    ];

    protected $casts = [
        'package_size' => 'decimal:2',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }
}
