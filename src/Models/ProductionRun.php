<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single "Production Order" -- batch counts per flavour for one
 * production run, matching the original Production Planner tab's top
 * section. Real unit counts are batch_size x batches, not entered
 * directly -- batch_size is a fixed constraint of the user's physical
 * process (mixing equipment, standard batch cut) shared across every
 * recipe in the run.
 *
 * @property int $id
 * @property string|null $name
 * @property int $batch_size
 * @property \Illuminate\Support\Carbon $run_date
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class ProductionRun extends Model
{
    protected $table = 'costing_production_runs';

    protected $fillable = [
        'name',
        'batch_size',
        'run_date',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'batch_size' => 'integer',
        'run_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'costing_production_run_recipe', 'production_run_id', 'recipe_id')
            ->withPivot('batches')
            ->withTimestamps();
    }

    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    public function totalUnits(): int
    {
        return (int) $this->recipes->sum(fn (Recipe $recipe) => $this->batch_size * $recipe->pivot->batches);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
