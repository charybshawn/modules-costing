<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ingredient_id
 * @property float $unit_size
 * @property float $units_on_hand
 * @property float|null $counted_on_hand
 * @property string|null $notes
 */
class InventoryItem extends Model
{
    protected $table = 'costing_inventory';

    protected $fillable = [
        'ingredient_id',
        'unit_size',
        'units_on_hand',
        'counted_on_hand',
        'notes',
    ];

    protected $casts = [
        'unit_size' => 'decimal:2',
        'units_on_hand' => 'decimal:2',
        'counted_on_hand' => 'decimal:2',
    ];

    protected $appends = ['on_hand'];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    /**
     * On Hand (g) = Unit Size x # Units, same as the original Inventory tab
     * -- unless a walk-in count entered against known per-brand package
     * sizes (Inventory/Edit.vue's "Stock on Hand, by Brand" table) has
     * recorded a raw counted total directly, in which case that's
     * authoritative instead. Keeps unit_size solely responsible for its
     * other job (purchase_size fallback in CalculateIngredientCosting)
     * rather than also being forced to divide evenly into whatever was
     * just physically counted.
     */
    public function getOnHandAttribute(): float
    {
        if ($this->counted_on_hand !== null) {
            return (float) $this->counted_on_hand;
        }

        return (float) $this->unit_size * (float) $this->units_on_hand;
    }
}
