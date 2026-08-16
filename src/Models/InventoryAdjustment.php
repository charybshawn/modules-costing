<?php

namespace Cultpantry\Costing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One audit-trail row for a single change to one source's (provider/brand)
 * on-hand quantity -- every write to PackageSize.quantity_on_hand (manual
 * recount, manual adjust, or the automatic deduction on production-run
 * completion) logs one of these via RecordInventoryAdjustment, so on_hand's
 * current value is never the only trace of how it got there. delta/
 * on_hand_before/on_hand_after are the specific source's quantity, not the
 * ingredient's aggregate -- every adjustment is tied to exactly one real
 * source. source_provider/source_brand are a snapshot taken at write time
 * (not just read live off packageSize) -- sources can be deleted
 * (package_size_id then goes null), and history has to stay readable
 * ("GFS -- Kraft") even after the row it pointed to is gone.
 *
 * @property int $id
 * @property int $ingredient_id
 * @property int|null $package_size_id
 * @property string $source_provider
 * @property string|null $source_brand
 * @property int|null $production_run_id
 * @property int|null $user_id
 * @property string $reason
 * @property float $delta
 * @property float $on_hand_before
 * @property float $on_hand_after
 * @property string|null $notes
 */
class InventoryAdjustment extends Model
{
    public const REASONS = ['recount', 'received', 'correction', 'production_run', 'production_run_reversal'];

    // The subset a user actually picks from a reason dropdown -- 'recount'
    // and 'production_run' are always derived by the system (recount mode,
    // CompleteProductionRun), never typed in directly.
    public const USER_SELECTABLE_REASONS = ['received', 'correction'];

    protected $table = 'costing_inventory_adjustments';

    protected $fillable = [
        'ingredient_id',
        'package_size_id',
        'source_provider',
        'source_brand',
        'production_run_id',
        'user_id',
        'reason',
        'delta',
        'on_hand_before',
        'on_hand_after',
        'notes',
    ];

    protected $casts = [
        'delta' => 'decimal:2',
        'on_hand_before' => 'decimal:2',
        'on_hand_after' => 'decimal:2',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function packageSize(): BelongsTo
    {
        return $this->belongsTo(PackageSize::class);
    }

    public function productionRun(): BelongsTo
    {
        return $this->belongsTo(ProductionRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
