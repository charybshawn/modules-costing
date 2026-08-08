<?php

namespace Cultpantry\Costing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One booked slot from a FoodCorridor "all bookings" CSV export. The
 * export lists one row per Space/Equipment reserved for a booking, not one
 * row per booking -- ImportKitchenRentalsFromCsv groups those rows back
 * into a single slot per (booking_title, starts_at, ends_at, space_name),
 * which is also this table's upsert key so re-importing an updated export
 * is safe.
 *
 * @property int $id
 * @property string $booking_title
 * @property string|null $booked_for
 * @property string|null $status
 * @property string|null $venue_name
 * @property string $space_name
 * @property array|null $equipment
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property float|null $booking_length
 * @property \Illuminate\Support\Carbon $booking_date
 * @property int|null $production_run_id
 */
class KitchenRental extends Model
{
    /**
     * FoodCorridor's own booking-status vocabulary -- also the option list
     * for the manual status override in KitchenRentalController::updateStatus(),
     * so a corrected status still means the same thing the CSV export would.
     */
    public const STATUSES = ['Approved', 'Submitted', 'Billed', 'Cancelled', 'Declined'];

    protected $table = 'costing_kitchen_rentals';

    protected $fillable = [
        'booking_title',
        'booked_for',
        'status',
        'venue_name',
        'space_name',
        'equipment',
        'starts_at',
        'ends_at',
        'booking_length',
        'booking_date',
        'production_run_id',
    ];

    protected $casts = [
        'equipment' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'booking_length' => 'decimal:1',
        // Explicit 'Y-m-d' format, not plain 'date' -- otherwise Eloquent
        // still serializes this column to a full 'Y-m-d H:i:s' string on
        // write while updateOrCreate's WHERE match uses a bare 'Y-m-d'
        // string, so the two never match, the upsert always falls through
        // to INSERT, and re-importing the same CSV 500s on the unique
        // constraint instead of updating the existing row.
        'booking_date' => 'date:Y-m-d',
    ];

    public function productionRun(): BelongsTo
    {
        return $this->belongsTo(ProductionRun::class);
    }
}
