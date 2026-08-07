<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $name
 * @property string|null $category
 * @property string $unit_type 'g'|'unit'
 * @property float $waste_percent
 * @property string|null $preferred_source
 * @property string|null $preferred_brand
 * @property string|null $notes
 * @property float|null $low_stock_threshold
 * @property float|null $weight_per_unit
 * @property string|null $byproduct_name
 */
class Ingredient extends Model
{
    protected $table = 'costing_ingredients';

    protected $fillable = [
        'name',
        'category',
        'unit_type',
        'waste_percent',
        'preferred_source',
        'preferred_brand',
        'notes',
        'low_stock_threshold',
        'weight_per_unit',
        'byproduct_name',
    ];

    protected $casts = [
        'waste_percent' => 'decimal:2',
        'low_stock_threshold' => 'decimal:2',
        'weight_per_unit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Every ingredient gets an inventory row (for notes) and an
        // "Unspecified" source row automatically -- mirrors the original
        // spreadsheet where every ingredient row appeared on both the
        // Ingredients and Inventory tabs, and guarantees there's always at
        // least one source to pick for an adjustment even before any real
        // provider/brand has been recorded.
        static::created(function (Ingredient $ingredient) {
            $ingredient->inventory()->create();
            $ingredient->packageSizes()->create([
                'provider' => 'Unspecified',
                'brand' => null,
                'package_size' => 1,
                'quantity_on_hand' => 0,
            ]);
        });
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistoryEntry::class, 'ingredient_id')->orderByDesc('purchased_at');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(InventoryItem::class, 'ingredient_id');
    }

    public function packageSizes(): HasMany
    {
        return $this->hasMany(PackageSize::class, 'ingredient_id');
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class, 'ingredient_id')->orderByDesc('created_at');
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'costing_ingredient_recipe', 'ingredient_id', 'recipe_id')
            ->withPivot('quantity_per_jar')
            ->withTimestamps();
    }

    /**
     * "kg" for gram-based ingredients, "unit"/"units" for packaging.
     */
    public function isGramBased(): bool
    {
        return $this->unit_type !== 'unit';
    }

    public function hasByproduct(): bool
    {
        return !empty($this->byproduct_name);
    }

    /**
     * $/100g, for comparing against grocery store shelf tags (which
     * usually price by 100g/100mL, not by kg). Weight and volume are
     * deliberately treated as 1:1 -- no per-ingredient density conversion,
     * just a straight $/kg / 10. Only meaningful for gram-based ingredients.
     */
    public function pricePer100g(?float $pricePerKg): ?float
    {
        if ($pricePerKg === null || !$this->isGramBased()) {
            return null;
        }

        return round($pricePerKg / 10, 4);
    }
}
