<?php

namespace Cultpantry\Costing\Database\Seeders;

use Cultpantry\Costing\Database\Seeders\Concerns\ReadsCsv;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Production runs -- run_date is stored in the CSV as "days offset from
 * today" (signed, can be negative), same relative-date reasoning as
 * PriceHistorySeeder, so a run that's a couple days out stays a couple
 * days out no matter when this is seeded. run_key is a CSV-only join
 * key linking production_runs.csv to production_run_recipes.csv (not a
 * DB column) -- needed because multiple runs can share the same name
 * (they're rental-driven, named after the booking).
 */
class ProductionPlanSeeder extends Seeder
{
    use ReadsCsv;

    public function run(): void
    {
        $runIdsByKey = [];
        foreach ($this->readCsv('production_runs.csv') as $row) {
            $runDate = Carbon::now()->addDays((int) $row['days_offset'])->toDateString();
            $name = $this->csvNullable($row['name']);

            $attributes = [
                'batch_size' => (int) $row['batch_size'],
                'notes' => $this->csvNullable($row['notes']),
            ];

            // Not updateOrCreate(['name' => ..., 'run_date' => $runDate], ...)
            // -- run_date is stored with a full datetime, not a bare date
            // (Eloquent's 'date' cast serializes to 'Y-m-d 00:00:00' on this
            // column), so an exact string match against '$runDate' alone
            // never hits and every reseed would create a duplicate row.
            // whereDate() compares by calendar day regardless of the
            // stored time component.
            $productionRun = ProductionRun::where('name', $name)->whereDate('run_date', $runDate)->first();

            $productionRun = $productionRun
                ? tap($productionRun)->update($attributes)
                : ProductionRun::create(['name' => $name, 'run_date' => $runDate, ...$attributes]);

            $runIdsByKey[$row['run_key']] = $productionRun->id;
        }

        $recipeIds = Recipe::pluck('id', 'name');

        $batchesByRun = [];
        foreach ($this->readCsv('production_run_recipes.csv') as $row) {
            $recipeId = $recipeIds[$row['recipe_name']] ?? null;
            if ($recipeId === null) {
                continue;
            }
            $batchesByRun[$row['run_key']][$recipeId] = [
                'batches' => (int) $row['batches'],
                'actual_units' => $this->csvInt($row['actual_units']),
            ];
        }

        foreach ($batchesByRun as $runKey => $syncData) {
            $runId = $runIdsByKey[$runKey] ?? null;
            if ($runId === null) {
                continue;
            }
            ProductionRun::find($runId)->recipes()->sync($syncData);
        }
    }
}
