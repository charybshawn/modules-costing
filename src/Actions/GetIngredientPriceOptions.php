<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Illuminate\Support\Collection;

/**
 * Every source (provider/brand combination) known for an ingredient, with
 * its most recent logged price -- for the Sources table on the Ingredients
 * page, where a preferred source is chosen regardless of which one is
 * currently cheapest. Includes sources that exist but have never had a
 * price logged (e.g. added from Inventory's stock modal), so this view is
 * the authoritative list of the ingredient's sources, not just of its
 * price history.
 */
class GetIngredientPriceOptions
{
    private const STALE_AFTER_DAYS = 7;

    /**
     * @return Collection<int, array{
     *     price_history_entry_id: int|null,
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
     *     units_per_case: int,
     *     quantity_on_hand: float,
     * }>
     */
    public function handle(Ingredient $ingredient): Collection
    {
        $ingredient->loadMissing('priceHistory.ingredient', 'priceHistory.packageSize', 'packageSizes');

        $cutoff = now()->subDays(self::STALE_AFTER_DAYS)->startOfDay();

        $priceRows = $ingredient->priceHistory
            // Most recent first (by date, then id as a tiebreaker), so
            // first() per group below is the latest. A zero-padded numeric
            // string avoids relying on PHP's array/Carbon comparison rules
            // for entries with a null purchased_at.
            ->sortByDesc(fn (PriceHistoryEntry $entry) => sprintf('%010d-%010d', $entry->purchased_at?->timestamp ?? 0, $entry->id))
            // Entries whose source was deleted all have a null
            // package_size_id -- falling back to provider|brand keeps them
            // grouped per real-world source instead of collapsing every
            // orphaned entry (across all providers) into one group whose
            // single newest row hides the rest.
            ->groupBy(fn (PriceHistoryEntry $entry) => $entry->package_size_id !== null
                ? 'ps:'.$entry->package_size_id
                : 'orphan:'.$entry->provider.'|'.($entry->brand ?? ''))
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
                    'is_preferred' => $this->isPreferred($ingredient, $entry->provider, $entry->brand),
                    'package_size' => $entry->packageSize !== null ? (float) $entry->packageSize->package_size : null,
                    'units_per_case' => $entry->packageSize !== null ? (int) $entry->packageSize->units_per_case : 1,
                    'quantity_on_hand' => $entry->packageSize !== null ? (float) $entry->packageSize->quantity_on_hand : 0.0,
                ];
            });

        // Sources with no price history at all (e.g. just added from the
        // Inventory stock modal) still belong in this list -- appended with
        // null price fields so they can be priced/preferred/deleted here.
        $pricedSourceIds = $priceRows->pluck('package_size_id')->filter()->all();

        $pricelessRows = $ingredient->packageSizes
            ->reject(fn (PackageSize $packageSize) => in_array($packageSize->id, $pricedSourceIds, true))
            ->map(fn (PackageSize $packageSize) => [
                'price_history_entry_id' => null,
                'package_size_id' => $packageSize->id,
                'provider' => $packageSize->provider,
                'brand' => $packageSize->brand,
                'price_per_unit' => null,
                'price_per_100g' => null,
                'total_price' => null,
                'qty' => null,
                'purchased_at' => null,
                'is_stale' => true,
                'is_preferred' => $this->isPreferred($ingredient, $packageSize->provider, $packageSize->brand),
                'package_size' => (float) $packageSize->package_size,
                'units_per_case' => (int) $packageSize->units_per_case,
                'quantity_on_hand' => (float) $packageSize->quantity_on_hand,
            ]);

        return $priceRows
            ->concat($pricelessRows)
            ->sortBy(fn (array $row) => $row['price_per_unit'] ?? INF)
            ->values();
    }

    /**
     * Strict pair equality, including null-brand rows: setPackageSize()'s
     * updateOrCreate is keyed on (ingredient_id, provider, brand), so that
     * pair already uniquely identifies one PackageSize row per ingredient.
     * A previous version treated a null preferred_brand as a "any brand
     * from that provider" wildcard, which let a source with no brand set
     * (e.g. "GFS") and a differently-branded source from the same provider
     * (e.g. "GFS -- Knorr") both show as preferred at once. Matching brand
     * strictly (not just "if set") keeps this 1:1.
     */
    private function isPreferred(Ingredient $ingredient, string $provider, ?string $brand): bool
    {
        return $ingredient->preferred_source === $provider
            && $ingredient->preferred_brand === $brand;
    }
}
