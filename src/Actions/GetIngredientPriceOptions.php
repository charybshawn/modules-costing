<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Illuminate\Support\Collection;

/**
 * Every wholesaler/brand combination ever logged for an ingredient,
 * collapsed to just the most recent entry per combination -- for the
 * "available prices" picker on the Ingredients page, where a preferred
 * brand is chosen regardless of which one is currently cheapest.
 */
class GetIngredientPriceOptions
{
    private const STALE_AFTER_DAYS = 7;

    /**
     * @return Collection<int, array{
     *     price_history_entry_id: int,
     *     package_size_id: int|null,
     *     provider: string,
     *     brand: string|null,
     *     price_per_unit: float|null,
     *     price_per_100g: float|null,
     *     total_price: float|null,
     *     qty: float|null,
     *     purchased_at: string|null,
     *     is_stale: bool,
     *     is_preferred: bool,
     *     package_size: float|null,
     *     quantity_on_hand: float,
     * }>
     */
    public function handle(Ingredient $ingredient): Collection
    {
        $ingredient->loadMissing('priceHistory.ingredient', 'priceHistory.packageSize');

        $cutoff = now()->subDays(self::STALE_AFTER_DAYS)->startOfDay();

        return $ingredient->priceHistory
            // Most recent first (by date, then id as a tiebreaker), so
            // first() per group below is the latest. A zero-padded numeric
            // string avoids relying on PHP's array/Carbon comparison rules
            // for entries with a null purchased_at.
            ->sortByDesc(fn (PriceHistoryEntry $entry) => sprintf('%010d-%010d', $entry->purchased_at?->timestamp ?? 0, $entry->id))
            ->groupBy(fn (PriceHistoryEntry $entry) => $entry->package_size_id)
            ->map(fn (Collection $group) => $group->first())
            ->map(function (PriceHistoryEntry $entry) use ($ingredient, $cutoff) {
                return [
                    'price_history_entry_id' => $entry->id,
                    'package_size_id' => $entry->package_size_id,
                    'provider' => $entry->provider,
                    'brand' => $entry->brand,
                    'price_per_unit' => $entry->price_per_unit,
                    'price_per_100g' => $ingredient->pricePer100g($entry->price_per_unit),
                    'total_price' => $entry->total_price !== null ? (float) $entry->total_price : null,
                    'qty' => $entry->qty !== null ? (float) $entry->qty : null,
                    'purchased_at' => optional($entry->purchased_at)->format('Y-m-d'),
                    'is_stale' => $entry->purchased_at === null || $entry->purchased_at->lt($cutoff),
                    'is_preferred' => $ingredient->preferred_source === $entry->provider
                        && $ingredient->preferred_brand === $entry->brand,
                    'package_size' => $entry->packageSize !== null ? (float) $entry->packageSize->package_size : null,
                    'quantity_on_hand' => $entry->packageSize !== null ? (float) $entry->packageSize->quantity_on_hand : 0.0,
                ];
            })
            ->sortBy(fn (array $row) => $row['price_per_unit'] ?? INF)
            ->values();
    }
}
