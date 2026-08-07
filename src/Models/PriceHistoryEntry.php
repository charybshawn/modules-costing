<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ingredient_id
 * @property int|null $package_size_id
 * @property \Illuminate\Support\Carbon|null $purchased_at
 * @property string $provider
 * @property string|null $brand
 * @property float|null $qty
 * @property float|null $total_price
 * @property string|null $sku
 * @property string|null $notes
 */
class PriceHistoryEntry extends Model
{
    protected $table = 'costing_price_history';

    protected $fillable = [
        'ingredient_id',
        'package_size_id',
        'purchased_at',
        'provider',
        'brand',
        'qty',
        'total_price',
        'sku',
        'notes',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'qty' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    /**
     * The Source (provider/brand) this entry's price actually describes.
     * provider/brand above are a snapshot of this at write time -- nullable
     * because the Source can later be deleted (nullOnDelete) without
     * destroying price history, same reasoning as InventoryAdjustment.
     */
    public function packageSize(): BelongsTo
    {
        return $this->belongsTo(PackageSize::class, 'package_size_id');
    }

    /**
     * $/kg (or $/unit for packaging) -- replaces the spreadsheet's
     * PRICE_PER_UNIT() Apps Script function / the original manual $/kg column.
     * Returns null instead of throwing a divide-by-zero for incomplete rows.
     */
    public function getPricePerUnitAttribute(): ?float
    {
        if (!$this->qty || !$this->total_price) {
            return null;
        }

        $qty = (float) $this->qty;
        $totalPrice = (float) $this->total_price;

        if ($qty <= 0) {
            return null;
        }

        $ingredient = $this->ingredient;
        $perUnit = $totalPrice / $qty;

        return ($ingredient && !$ingredient->isGramBased()) ? $perUnit : $perUnit * 1000;
    }

    /**
     * $/100g, for comparing against grocery store shelf tags -- see
     * Ingredient::pricePer100g().
     */
    public function getPricePer100gAttribute(): ?float
    {
        return $this->ingredient?->pricePer100g($this->price_per_unit);
    }
}
