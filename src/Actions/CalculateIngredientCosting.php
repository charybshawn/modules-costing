<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\PriceHistoryEntry;

/**
 * Ports the original spreadsheet's Ingredients auto-columns (E/F/G/H/J/K)
 * and Apps Script equivalents (WEEKLY_PRICE / EFFECTIVE_PRICE / SOURCE_USED /
 * LAST_PRICE_DATE / PURCHASE_SIZE / PURCHASE_UNIT) into one Action.
 *
 * Finds the cheapest (or preferred-source) price logged for an ingredient
 * within the last 7 days, waste-adjusts it, and builds a human-readable
 * purchase unit label. Returns a clean "no price this week" status instead
 * of the original's #DIV/0! / #REF! errors when data is missing.
 */
class CalculateIngredientCosting
{
    private const LOOKBACK_DAYS = 7;

    /**
     * @return array{
     *     weekly_price: float|null,
     *     effective_price: float|null,
     *     source_used: string|null,
     *     last_price_date: string|null,
     *     purchase_size: float,
     *     purchase_unit: string|null,
     *     status: 'ok'|'no_price_this_week',
     *     stale_price: float|null,
     *     stale_effective_price: float|null,
     *     stale_source: string|null,
     *     stale_brand: string|null,
     *     stale_price_date: string|null,
     *     price_per_100g: float|null,
     *     stale_price_per_100g: float|null,
     * }
     *
     * purchase_size/purchase_unit always reflect the ingredient's real
     * minimum purchasable package size for whichever provider/brand won
     * this week's price (or the most recent one, if none is fresh):
     * a brand-specific PackageSize row when one has been logged for that
     * exact provider/brand, else Ingredient.weight_per_unit (the generic,
     * ingredient-level "typical size" fallback), else 1.0/null.
     * InventoryItem.unit_size is deliberately not consulted here -- it's
     * strictly an on-hand stock-counting concern (unit_size x
     * units_on_hand, or counted_on_hand directly), a different question
     * from "what's the real order minimum," and having both unit_size and
     * weight_per_unit answer that second question was itself a source of
     * confusion. Price History's own qty never drives this either -- a
     * logged price's qty might be a one-off (e.g. a sample size) and is
     * never a reliable statement of how the ingredient is actually sold,
     * so it only ever influences cost-per-kg, not purchase sizing.
     *
     * The weekly_price/effective_price/status fields only ever reflect a
     * price within the last 7 days -- that's what recipe/production
     * costing actually trusts. stale_* fields are purely for display: the
     * most recently logged price regardless of age (respecting a preferred
     * source/brand if one is set), so the UI can show "here's the last
     * known price, but it needs rechecking" instead of just going blank.
     */
    public function handle(Ingredient $ingredient): array
    {
        // Defensive, not just an optimization: price_per_unit below reads
        // $entry->ingredient, and this Action has no control over whether
        // its caller remembered to eager-load that nested relation. A
        // no-op if the caller already did (as the real controllers now
        // do); protects any caller that doesn't, including direct/unit
        // usage, from a LazyLoadingViolationException.
        $ingredient->loadMissing('priceHistory.ingredient', 'inventory', 'packageSizes');

        $cutoff = now()->subDays(self::LOOKBACK_DAYS)->startOfDay();

        // Already ordered newest-first by the priceHistory() relation.
        $measurableEntries = $ingredient->priceHistory
            ->filter(fn (PriceHistoryEntry $entry) => $entry->purchased_at !== null
                && $entry->qty
                && $entry->total_price
                && $entry->price_per_unit !== null);

        if ($ingredient->preferred_source) {
            $measurableEntries = $measurableEntries->where('provider', $ingredient->preferred_source);

            if ($ingredient->preferred_brand) {
                $measurableEntries = $measurableEntries->where('brand', $ingredient->preferred_brand);
            }
        }

        $entries = $measurableEntries->filter(
            fn (PriceHistoryEntry $entry) => $entry->purchased_at->greaterThanOrEqualTo($cutoff)
        );

        if ($entries->isEmpty()) {
            $latest = $measurableEntries->first();
            $waste = max((float) $ingredient->waste_percent, 0.01);
            [$packageSize, $packageUnitLabel] = $this->resolvePackageSize($ingredient, $latest?->provider, $latest?->brand);

            return [
                'weekly_price' => null,
                'effective_price' => null,
                'source_used' => null,
                'last_price_date' => null,
                'purchase_size' => $packageSize,
                'purchase_unit' => $packageUnitLabel,
                'status' => 'no_price_this_week',
                'stale_price' => $latest ? round($latest->price_per_unit, 4) : null,
                'stale_effective_price' => $latest ? round($latest->price_per_unit * (100 / $waste), 4) : null,
                'stale_source' => $latest?->provider,
                'stale_brand' => $latest?->brand,
                'stale_price_date' => $latest ? $latest->purchased_at->format('Y-m-d') : null,
                'price_per_100g' => null,
                'stale_price_per_100g' => $latest ? $ingredient->pricePer100g(round($latest->price_per_unit, 4)) : null,
            ];
        }

        // Preferred source: most recent entry for that provider.
        // Otherwise: cheapest entry across all providers within the window.
        $best = $ingredient->preferred_source
            ? $entries->sortByDesc(fn (PriceHistoryEntry $e) => $e->purchased_at)->first()
            : $entries->sortBy(fn (PriceHistoryEntry $e) => $e->price_per_unit)->first();

        $weeklyPrice = $best->price_per_unit;
        $waste = max((float) $ingredient->waste_percent, 0.01);
        $effectivePrice = $weeklyPrice * (100 / $waste);
        [$packageSize, $packageUnitLabel] = $this->resolvePackageSize($ingredient, $best->provider, $best->brand);

        return [
            'weekly_price' => round($weeklyPrice, 4),
            'effective_price' => round($effectivePrice, 4),
            'source_used' => $best->provider,
            'last_price_date' => $best->purchased_at->format('Y-m-d'),
            'purchase_size' => $packageSize,
            'purchase_unit' => $packageUnitLabel,
            'status' => 'ok',
            'stale_price' => null,
            'stale_effective_price' => null,
            'stale_source' => null,
            'stale_brand' => null,
            'stale_price_date' => null,
            'price_per_100g' => $ingredient->pricePer100g(round($weeklyPrice, 4)),
            'stale_price_per_100g' => null,
        ];
    }

    /**
     * The real minimum purchasable package size for this ingredient, for
     * the given winning provider/brand -- a brand-specific PackageSize row
     * (provider+brand exact match) when one has been logged, else
     * Ingredient.weight_per_unit, else no package size at all.
     *
     * @return array{0: float, 1: string|null}
     */
    private function resolvePackageSize(Ingredient $ingredient, ?string $provider, ?string $brand): array
    {
        if ($provider !== null) {
            $brandSize = $ingredient->packageSizes->first(
                fn (PackageSize $ps) => $ps->provider === $provider && $ps->brand === $brand
            );

            if ($brandSize !== null && (float) $brandSize->package_size > 0) {
                $size = (float) $brandSize->package_size;
                $source = $brand ? "{$provider} -- {$brand}" : $provider;

                return [$size, $this->formatPackageSize($ingredient, $size, $source)];
            }
        }

        if ($ingredient->weight_per_unit !== null) {
            $weightPerUnit = (float) $ingredient->weight_per_unit;

            return [$weightPerUnit, $this->formatPackageSize($ingredient, $weightPerUnit, 'package size')];
        }

        return [1.0, null];
    }

    private function formatPackageSize(Ingredient $ingredient, float $size, string $source): string
    {
        if ($ingredient->isGramBased()) {
            $kg = rtrim(rtrim(number_format($size / 1000, 2), '0'), '.');
            $sizeLabel = "{$kg} kg";
        } else {
            $units = (int) $size;
            $sizeLabel = "{$units} unit".($units === 1 ? '' : 's');
        }

        return "{$sizeLabel} ({$source})";
    }
}
