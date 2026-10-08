<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;

/**
 * The costing module's data as one portable JSON document, in whichever
 * sections are asked for. Records refer to each other by name (an
 * ingredient's name, a recipe's name, a run's batch code), never by id, so
 * a file can be imported into another install -- or back into this one --
 * by ImportCostingData.
 */
class ExportCostingData
{
    public const FORMAT = 'cultpantry-costing';

    public const VERSION = 1;

    /** Import order: each section only refers to the ones before it. */
    public const SECTIONS = ['ingredients', 'price_history', 'inventory', 'recipes', 'production_runs'];

    /**
     * @param  array<int, string>  $sections
     * @return array<string, mixed>
     */
    public function handle(array $sections): array
    {
        $sections = array_values(array_intersect(self::SECTIONS, $sections));

        $document = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'sections' => $sections,
        ];

        foreach ($sections as $section) {
            $document[$section] = match ($section) {
                'ingredients' => $this->ingredients(),
                'price_history' => $this->priceHistory(),
                'inventory' => $this->inventory(),
                'recipes' => $this->recipes(),
                'production_runs' => $this->productionRuns(),
            };
        }

        return $document;
    }

    /**
     * Each ingredient's definition: details, where it's bought, and -- for
     * a house-made one -- what it's made from.
     */
    private function ingredients(): array
    {
        return Ingredient::with('packageSizes', 'components')->orderBy('name')->get()
            ->map(fn (Ingredient $ingredient) => [
                'name' => $ingredient->name,
                'category' => $ingredient->category,
                'unit_type' => $ingredient->unit_type,
                'waste_percent' => (float) $ingredient->waste_percent,
                'preferred_source' => $ingredient->preferred_source,
                'preferred_brand' => $ingredient->preferred_brand,
                'byproduct_name' => $ingredient->byproduct_name,
                'notes' => $ingredient->notes,
                'is_house_made' => $ingredient->is_house_made,
                'cook_down_percent' => $ingredient->cook_down_percent !== null ? (float) $ingredient->cook_down_percent : null,
                'made_from' => $ingredient->components->sortBy('name')->map(fn (Ingredient $component) => [
                    'ingredient' => $component->name,
                    'quantity' => (float) $component->pivot->quantity,
                ])->values()->all(),
                'sources' => $ingredient->packageSizes->sortBy(['provider', 'brand'])->map(fn (PackageSize $source) => [
                    'provider' => $source->provider,
                    'brand' => $source->brand,
                    'package_size' => (float) $source->package_size,
                    'units_per_case' => (int) $source->units_per_case,
                ])->values()->all(),
            ])->values()->all();
    }

    private function priceHistory(): array
    {
        return PriceHistoryEntry::with('ingredient:id,name')->orderBy('purchased_at')->orderBy('id')->get()
            ->filter(fn (PriceHistoryEntry $entry) => $entry->ingredient !== null)
            ->map(fn (PriceHistoryEntry $entry) => [
                'ingredient' => $entry->ingredient->name,
                'purchased_at' => $entry->purchased_at?->toDateString(),
                'provider' => $entry->provider,
                'brand' => $entry->brand,
                'qty' => $entry->qty !== null ? (float) $entry->qty : null,
                'total_price' => $entry->total_price !== null ? (float) $entry->total_price : null,
                'priced_as_case' => (bool) $entry->priced_as_case,
                'sku' => $entry->sku,
                'notes' => $entry->notes,
            ])->values()->all();
    }

    /** Stock on hand per source -- only the sources holding any. */
    private function inventory(): array
    {
        return PackageSize::with('ingredient:id,name')->where('quantity_on_hand', '!=', 0)->get()
            ->filter(fn (PackageSize $source) => $source->ingredient !== null)
            ->sortBy(fn (PackageSize $source) => [$source->ingredient->name, $source->provider, $source->brand])
            ->map(fn (PackageSize $source) => [
                'ingredient' => $source->ingredient->name,
                'provider' => $source->provider,
                'brand' => $source->brand,
                'quantity_on_hand' => (float) $source->quantity_on_hand,
            ])->values()->all();
    }

    /** Recipes and their lines. The storefront product link stays behind: it's specific to one install. */
    private function recipes(): array
    {
        return Recipe::with('ingredients')->orderBy('name')->get()
            ->map(fn (Recipe $recipe) => [
                'name' => $recipe->name,
                'notes' => $recipe->notes,
                'is_active' => $recipe->is_active,
                'sell_price' => $recipe->sell_price !== null ? (float) $recipe->sell_price : null,
                'fill_size_g' => $recipe->fill_size_g !== null ? (float) $recipe->fill_size_g : null,
                'preferred_batch_size' => $recipe->preferred_batch_size,
                'cost_buffer_percent' => $recipe->cost_buffer_percent !== null ? (float) $recipe->cost_buffer_percent : null,
                'min_stock_threshold' => $recipe->min_stock_threshold,
                'lines' => $recipe->ingredients->sortBy('name')->map(fn (Ingredient $ingredient) => [
                    'ingredient' => $ingredient->name,
                    'quantity' => (float) $ingredient->pivot->quantity_per_jar,
                    'byproduct' => (bool) $ingredient->pivot->is_byproduct,
                ])->values()->all(),
            ])->values()->all();
    }

    /** Runs with their batches -- and, for completed ones, their saved records. */
    private function productionRuns(): array
    {
        return ProductionRun::with('recipes')->orderBy('run_date')->orderBy('id')->get()
            ->map(fn (ProductionRun $run) => [
                'name' => $run->name,
                'type' => $run->type,
                'run_date' => $run->run_date?->toDateString(),
                'batch_size' => $run->batch_size,
                'notes' => $run->notes,
                'completed_at' => $run->completed_at?->toIso8601String(),
                'recipes' => $run->recipes->sortBy('name')->map(fn (Recipe $recipe) => [
                    'recipe' => $recipe->name,
                    'batches' => (int) $recipe->pivot->batches,
                    'actual_units' => $recipe->pivot->actual_units !== null ? (int) $recipe->pivot->actual_units : null,
                ])->values()->all(),
                'purchase_order_record' => $run->purchase_order_record,
                'recipe_sheet_record' => $run->recipe_sheet_record,
            ])->values()->all();
    }
}
