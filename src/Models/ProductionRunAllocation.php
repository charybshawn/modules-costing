<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many units of one recipe size a production run fills from its mix.
 *
 * @property int $id
 * @property int $production_run_id
 * @property int $recipe_size_id
 * @property int $units
 * @property int|null $actual_units
 */
class ProductionRunAllocation extends Model
{
    protected $table = 'costing_production_run_allocations';

    protected $fillable = [
        'production_run_id',
        'recipe_size_id',
        'units',
        'actual_units',
    ];

    protected $casts = [
        'units' => 'integer',
        'actual_units' => 'integer',
    ];

    public function productionRun(): BelongsTo
    {
        return $this->belongsTo(ProductionRun::class);
    }

    public function recipeSize(): BelongsTo
    {
        return $this->belongsTo(RecipeSize::class);
    }

    /** Made once known, else planned. */
    public function producedUnits(): int
    {
        return $this->actual_units ?? $this->units;
    }
}
