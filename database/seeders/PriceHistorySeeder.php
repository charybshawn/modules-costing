<?php

namespace Cultpantry\Costing\Database\Seeders;

use Cultpantry\Costing\Database\Seeders\Concerns\ReadsCsv;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Logged prices, one per row -- same shape store()/updatePrice() write from
 * the UI. Dates are stored in the CSV as "days ago from today" (blank =
 * purchased_at null, an intentionally incomplete row) rather than fixed
 * calendar dates, computed once from each entry's real purchased_at at the
 * time this seeder was written -- so the 7-day "price this week" freshness
 * window behaves the same regardless of what day this actually runs, not
 * just on the day it was captured.
 */
class PriceHistorySeeder extends Seeder
{
    use ReadsCsv;

    public function run(): void
    {
        $ingredientIds = Ingredient::pluck('id', 'name');

        foreach ($this->readCsv('price_history.csv') as $row) {
            $ingredientId = $ingredientIds[$row['ingredient_name']] ?? null;
            if ($ingredientId === null) {
                continue;
            }

            $packageSize = PackageSize::where('ingredient_id', $ingredientId)
                ->where('provider', $row['provider'])
                ->where('brand', $this->csvNullable($row['brand']))
                ->first();

            $daysAgo = $this->csvInt($row['days_ago']);

            PriceHistoryEntry::create([
                'ingredient_id' => $ingredientId,
                'package_size_id' => $packageSize?->id,
                'purchased_at' => $daysAgo === null ? null : Carbon::now()->subDays($daysAgo)->toDateString(),
                'provider' => $row['provider'],
                'brand' => $this->csvNullable($row['brand']),
                'qty' => $this->csvFloat($row['qty']),
                'total_price' => $this->csvFloat($row['total_price']),
                'sku' => $this->csvNullable($row['sku']),
                'notes' => $this->csvNullable($row['notes']),
            ]);
        }
    }
}
