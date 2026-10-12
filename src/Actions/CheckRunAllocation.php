<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Models\RecipeSize;

/**
 * Works out, for each recipe in a run, how much product its batches make
 * and how much of that the chosen fills use up.
 *
 * Volume = the recipe's yield in grams x the run's batch size x its batches
 * (the same amounts CalculateProductionPlan shops for). Each allocated unit
 * of a size takes that size's fill weight out of it. A recipe is short when
 * the fills ask for more than the batches make.
 */
class CheckRunAllocation
{
    /**
     * @param  iterable<int, array{recipe_size_id: int, units: int}>|null  $allocations
     *     the fills to check; defaults to those saved on the run
     * @return array<int, array{
     *     recipe_id: int,
     *     recipe_name: string,
     *     volume_g: float,
     *     allocated_g: float,
     *     remaining_g: float,
     *     fits: bool,
     *     message: string|null,
     *     sizes: array<int, array{recipe_size_id: int, label: string, fill_size_g: float, units: int, grams: float}>,
     * }> keyed by recipe id
     */
    public function handle(ProductionRun $productionRun, ?iterable $allocations = null): array
    {
        $productionRun->loadMissing('recipes', 'allocations');

        $allocations = collect($allocations ?? $productionRun->allocations->map(fn ($allocation) => [
            'recipe_size_id' => $allocation->recipe_size_id,
            'units' => $allocation->units,
        ]))->filter(fn (array $row) => (int) $row['units'] > 0);

        $sizes = RecipeSize::whereIn('id', $allocations->pluck('recipe_size_id')->unique())->get()->keyBy('id');
        $unitsBySize = $allocations->groupBy('recipe_size_id')->map(fn ($rows) => (int) $rows->sum('units'));

        $results = [];

        /** @var Recipe $recipe */
        foreach ($productionRun->recipes as $recipe) {
            $batches = (int) $recipe->pivot->batches;
            if ($batches <= 0) {
                continue;
            }

            $volume = $recipe->yieldGrams() * $productionRun->batch_size * $batches;

            $rows = [];
            $allocated = 0.0;
            foreach ($sizes->where('recipe_id', $recipe->id) as $size) {
                $units = $unitsBySize[$size->id] ?? 0;
                $grams = $units * (float) $size->fill_size_g;
                $allocated += $grams;
                $rows[] = [
                    'recipe_size_id' => $size->id,
                    'label' => $size->label,
                    'fill_size_g' => (float) $size->fill_size_g,
                    'units' => $units,
                    'grams' => $grams,
                ];
            }

            $remaining = $volume - $allocated;
            $fits = $remaining >= -0.005;

            $results[$recipe->id] = [
                'recipe_id' => $recipe->id,
                'recipe_name' => $recipe->name,
                'volume_g' => round($volume, 2),
                'allocated_g' => round($allocated, 2),
                'remaining_g' => round($remaining, 2),
                'fits' => $fits,
                'message' => $fits ? null : $this->shortMessage($recipe, $rows, $volume, $allocated),
                'sizes' => $rows,
            ];
        }

        return $results;
    }

    /**
     * The first problem found across the whole run, or null when every
     * recipe's fills fit and every allocated size belongs to a recipe that
     * has batches in the run.
     */
    public function firstProblem(ProductionRun $productionRun, ?iterable $allocations = null): ?string
    {
        $allocations = collect($allocations ?? $productionRun->allocations->map(fn ($allocation) => [
            'recipe_size_id' => $allocation->recipe_size_id,
            'units' => $allocation->units,
        ]))->filter(fn (array $row) => (int) $row['units'] > 0);

        $results = $this->handle($productionRun, $allocations);

        foreach (RecipeSize::with('recipe')->whereIn('id', $allocations->pluck('recipe_size_id'))->get() as $size) {
            if (!isset($results[$size->recipe_id])) {
                return "{$size->recipe->name} has fills but no batches in this run.";
            }
        }

        foreach ($results as $result) {
            if (!$result['fits']) {
                return $result['message'];
            }

            // A production run's mix has to end up in something.
            if ($productionRun->type === 'production' && $result['allocated_g'] <= 0) {
                return "{$result['recipe_name']} has batches but no fills allocated -- choose how many of each size to fill.";
            }
        }

        return null;
    }

    private function shortMessage(Recipe $recipe, array $rows, float $volume, float $allocated): string
    {
        $parts = collect($rows)
            ->filter(fn (array $row) => $row['units'] > 0)
            ->map(fn (array $row) => "{$row['units']} x {$this->grams($row['fill_size_g'])}g")
            ->implode(' + ');

        return "{$recipe->name}: {$parts} needs {$this->grams($allocated)}g but its batches make {$this->grams($volume)}g"
            .' -- short by '.$this->grams($allocated - $volume).'g. Add batches or fill fewer units.';
    }

    private function grams(float $grams): string
    {
        return rtrim(rtrim(number_format($grams, 2), '0'), '.');
    }
}
