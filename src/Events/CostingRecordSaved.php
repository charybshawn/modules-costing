<?php

namespace Cultpantry\Costing\Events;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\KitchenRental;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a costing record is created or updated -- the package's only
 * "hook" for audit logging. Dispatched via the plain event()/::dispatch()
 * helper, so it costs nothing and requires no container binding when
 * nothing is listening (unlike a Contract, which must be bound or the app
 * breaks) -- a host app opts into logging this by registering a listener,
 * never something this package requires to function. See
 * CostingIntegrationServiceProvider in the host app for the reference
 * integration (writes into App\Models\Event via App\Actions\RecordEvent).
 */
final class CostingRecordSaved
{
    use Dispatchable;

    /**
     * @param  string  $action  'created' | 'updated'
     * @param  array<string, mixed>  $changes  created: full attribute snapshot.
     *     updated: [attribute => ['old' => mixed, 'new' => mixed]] for
     *     changed attributes only.
     * @param  array<string, mixed>  $context  Free-form extra tagging, e.g.
     *     ['source' => 'bulk_action', 'bulk_action' => 'deactivate'].
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelId,
        public readonly string $action,
        public readonly string $label,
        public readonly array $changes,
        public readonly ?int $actorId,
        public readonly array $context = [],
    ) {}

    public static function forCreated(Model $model, ?int $actorId, array $context = []): self
    {
        return new self(
            modelClass: get_class($model),
            modelId: $model->getKey(),
            action: 'created',
            label: self::labelFor($model),
            changes: $model->getAttributes(),
            actorId: $actorId,
            context: $context,
        );
    }

    /**
     * Call *after* $model->update(...) -- Eloquent still has the pre-save
     * values available via getOriginal() at that point, paired with
     * getChanges() for exactly what actually changed.
     */
    public static function forUpdated(Model $model, ?int $actorId, array $context = []): self
    {
        $changes = [];
        foreach ($model->getChanges() as $key => $new) {
            if ($key === 'updated_at') {
                continue;
            }
            $changes[$key] = ['old' => $model->getOriginal($key), 'new' => $new];
        }

        return new self(
            modelClass: get_class($model),
            modelId: $model->getKey(),
            action: 'updated',
            label: self::labelFor($model),
            changes: $changes,
            actorId: $actorId,
            context: $context,
        );
    }

    /**
     * For an update whose diff isn't visible on $model's own getChanges()
     * -- e.g. CompleteProductionRun/UncompleteProductionRun mutate a
     * separately re-fetched, locked copy of the row internally, so the
     * controller's own instance never sees itself as "dirty" even after
     * refresh(). The caller already knows exactly what changed semantically
     * in these cases, so it's passed straight through rather than guessed.
     */
    public static function forCustomUpdate(Model $model, array $changes, ?int $actorId, array $context = []): self
    {
        return new self(
            modelClass: get_class($model),
            modelId: $model->getKey(),
            action: 'updated',
            label: self::labelFor($model),
            changes: $changes,
            actorId: $actorId,
            context: $context,
        );
    }

    private static function labelFor(Model $model): string
    {
        return match (true) {
            $model instanceof Ingredient, $model instanceof Recipe => $model->name,
            $model instanceof PriceHistoryEntry => trim(($model->ingredient?->name ?? 'Unknown ingredient')." — {$model->provider}"),
            $model instanceof ProductionRun => $model->name ?? optional($model->run_date)->format('Y-m-d') ?? "Run #{$model->getKey()}",
            $model instanceof KitchenRental => $model->booking_title,
            default => (string) $model->getKey(),
        };
    }
}
