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
 * InventoryAdjustment). A source is always a real, named provider (brand
 * optional) -- an ingredient with no known source simply has no rows here
 * yet, no placeholder fallback.
 *
 * package_size is always the size of ONE individual package -- recount,
 * quick-adjust, and quantity_on_hand all work at that granularity, never
 * cases. units_per_case is a purchasing constraint only ("GFS only sells
 * this in cases of 4"): it affects how CalculateProductionPlan rounds a
 * shopping-list shortfall up to a whole case, and how a re-logged/newly
 * seeded price's qty represents "one case" rather than one package, but
 * never how physical stock is counted.
 *
 * @property int $id
 * @property int $ingredient_id
 * @property string $provider
 * @property string|null $brand
 * @property float $package_size
 * @property int $units_per_case
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
        'units_per_case',
        'quantity_on_hand',
    ];

    protected $casts = [
        'package_size' => 'decimal:2',
        'units_per_case' => 'integer',
        'quantity_on_hand' => 'decimal:2',
    ];

    protected $appends = ['packages', 'case_total'];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    /**
     * How many whole/partial packages quantity_on_hand represents -- the
     * display-facing inverse of "packages x size = quantity" data entry.
     * Always individual packages, never cases -- that's the physical
     * count granularity everywhere in this app, cases are a purchasing
     * concept only (see case_total below).
     */
    public function getPackagesAttribute(): float
    {
        $size = (float) $this->package_size;

        return $size > 0 ? round((float) $this->quantity_on_hand / $size, 3) : 0.0;
    }

    /**
     * The total quantity in one case -- package_size x units_per_case.
     * Equals package_size itself when units_per_case is 1 (not sold by
     * the case), so every caller that needs "the sellable/billable unit"
     * can read this instead of branching on units_per_case itself.
     */
    public function getCaseTotalAttribute(): float
    {
        return (float) $this->package_size * max(1, (int) $this->units_per_case);
    }
}
