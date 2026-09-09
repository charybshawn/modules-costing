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
 * Fired after a costing record is deleted. See CostingRecordSaved's
 * docblock for the decoupling rationale -- same properties here.
 */
final class CostingRecordDeleted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $attributes  Full snapshot of the
     *     deleted row -- the caller must capture this from the model
     *     *before* calling ->delete(), not derive it from this event.
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelId,
        public readonly string $label,
        public readonly array $attributes,
        public readonly ?int $actorId,
        public readonly array $context = [],
    ) {}

    /**
     * Call *before* $model->delete() -- label/attributes are captured from
     * the still-live instance.
     */
    public static function forModel(Model $model, ?int $actorId, array $context = []): self
    {
        return new self(
            modelClass: get_class($model),
            modelId: $model->getKey(),
            label: self::labelFor($model),
            attributes: $model->getAttributes(),
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
