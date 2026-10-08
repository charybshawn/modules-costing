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
 * @property string|null $byproduct_name
 * @property bool $is_house_made
 * @property float|null $cook_down_percent
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
        'byproduct_name',
        'is_house_made',
        'cook_down_percent',
    ];

    protected $casts = [
        'waste_percent' => 'decimal:2',
        'is_house_made' => 'boolean',
        'cook_down_percent' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Every ingredient gets an inventory row (for notes) automatically --
        // mirrors the original spreadsheet where every ingredient row
        // appeared on both the Ingredients and Inventory tabs. Sources
        // (PackageSize rows) are NOT auto-created -- a source is a real,
        // named provider/brand or it doesn't exist yet; a new ingredient
        // simply starts with zero sources until one is actually added.
        static::created(function (Ingredient $ingredient) {
            $ingredient->inventory()->create();
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
     * What a house-made ingredient is made from, per prep batch (pivot
     * quantity, in grams or units). Empty for bought ingredients.
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'costing_ingredient_components', 'ingredient_id', 'component_ingredient_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Total weight of what goes into one prep batch -- the weighed
     * (gram-based) components only; unit-counted ones have no weight.
     */
    public function batchInputGrams(): float
    {
        $this->loadMissing('components');

        return (float) $this->components
            ->filter(fn (Ingredient $component) => $component->isGramBased())
            ->sum(fn (Ingredient $component) => (float) $component->pivot->quantity);
    }

    /**
     * What one prep batch weighs once cooked: its input weight times the
     * cook-down percentage. Null until both are known, so it's simply
     * unpriced rather than wrong.
     */
    public function yieldGrams(): ?float
    {
        if (!$this->is_house_made || $this->cook_down_percent === null) {
            return null;
        }

        $yield = $this->batchInputGrams() * (float) $this->cook_down_percent / 100;

        return $yield > 0 ? $yield : null;
    }

    /**
     * House-made ingredients this one goes into.
     */
    public function usedInHouseMade(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'costing_ingredient_components', 'component_ingredient_id', 'ingredient_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * "kg" for gram-based ingredients, "unit"/"units" for packaging.
     */
    public function isGramBased(): bool
    {
        return $this->unit_type !== 'unit';
    }

    /**
     * Jars, lids, labels -- the "Packaging" category (free text, so matched
     * case-insensitively). Recipe line lists group these after the food.
     */
    public function isPackaging(): bool
    {
        return strcasecmp(trim((string) $this->category), 'Packaging') === 0;
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
