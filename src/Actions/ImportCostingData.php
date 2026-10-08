<?php

namespace Cultpantry\Costing\Actions;

use Cultpantry\Costing\Events\CostingRecordDeleted;
use Cultpantry\Costing\Events\CostingRecordSaved;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Brings an ExportCostingData file back in, section by section, matching
 * everything by name.
 *
 * - merge: existing records with the same name are updated, new ones added,
 *   nothing deleted; price entries already present aren't duplicated.
 * - wipe:  each chosen section is emptied first, then filled from the file.
 *
 * preview() reports what apply() would do without changing anything.
 * Completed runs come in as records only: no stock is deducted and no
 * finished goods are credited.
 */
class ImportCostingData
{
    public const MODES = ['merge', 'wipe'];

    public function __construct(
        private readonly RecordInventoryAdjustment $recordInventoryAdjustment,
    ) {}

    /** Throws if this isn't a costing export this version can read. */
    public function validateDocument(mixed $document): array
    {
        if (!is_array($document) || ($document['format'] ?? null) !== ExportCostingData::FORMAT) {
            throw new InvalidArgumentException('This isn\'t a costing export file.');
        }
        if ((int) ($document['version'] ?? 0) > ExportCostingData::VERSION) {
            throw new InvalidArgumentException('This file comes from a newer version of the costing module.');
        }

        return $document;
    }

    /** The sections present in the file, in import order. */
    public function sectionsIn(array $document): array
    {
        return array_values(array_filter(ExportCostingData::SECTIONS, fn (string $section) => is_array($document[$section] ?? null)));
    }

    /**
     * Per section: how many records are new / already exist, what a wipe
     * would delete, and any problems (references the import can't resolve).
     *
     * @return array<string, array{rows: int, new: int, existing: int, would_delete: int, problems: array<int, string>}>
     */
    public function preview(array $document, array $sections, string $mode): array
    {
        $sections = $this->chosen($document, $sections);
        $wipe = fn (string $section) => $mode === 'wipe' && in_array($section, $sections, true);
        [$ingredientNames, $recipeNames] = $this->namesAfterImport($document, $sections, $mode);

        $preview = [];

        foreach ($sections as $section) {
            $rows = $document[$section];
            $problems = [];
            $new = 0;
            $existing = 0;

            switch ($section) {
                case 'ingredients':
                    $current = $wipe('ingredients') ? [] : Ingredient::pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
                    foreach ($rows as $row) {
                        in_array(mb_strtolower((string) ($row['name'] ?? '')), $current, true) ? $existing++ : $new++;
                        if (!in_array($row['unit_type'] ?? null, ['g', 'unit'], true)) {
                            $problems[] = "{$row['name']}: unknown measure '".($row['unit_type'] ?? '')."'.";
                        }
                        foreach ($row['made_from'] ?? [] as $line) {
                            if (!isset($ingredientNames[mb_strtolower($line['ingredient'])])) {
                                $problems[] = "{$row['name']} is made from '{$line['ingredient']}', which isn't in the file or this install -- that line will be skipped.";
                            }
                        }
                    }
                    $wouldDelete = $wipe('ingredients') ? Ingredient::count() : 0;
                    break;

                case 'price_history':
                    $currentKeys = $wipe('price_history') ? [] : PriceHistoryEntry::with('ingredient:id,name')->get()
                        ->map(fn (PriceHistoryEntry $entry) => $this->priceKey($entry->ingredient?->name, $entry->purchased_at?->toDateString(), $entry->provider, $entry->brand, $entry->qty, $entry->total_price))
                        ->flip()->all();
                    foreach ($rows as $row) {
                        if (!isset($ingredientNames[mb_strtolower((string) ($row['ingredient'] ?? ''))])) {
                            $problems[] = "A price for '{$row['ingredient']}' -- not in the file or this install, skipped.";
                            continue;
                        }
                        isset($currentKeys[$this->priceKey($row['ingredient'], $row['purchased_at'] ?? null, $row['provider'] ?? null, $row['brand'] ?? null, $row['qty'] ?? null, $row['total_price'] ?? null)])
                            ? $existing++ : $new++;
                    }
                    $wouldDelete = $wipe('price_history') ? PriceHistoryEntry::count() : 0;
                    break;

                case 'inventory':
                    foreach ($rows as $row) {
                        isset($ingredientNames[mb_strtolower((string) ($row['ingredient'] ?? ''))])
                            ? $existing++
                            : $problems[] = "Stock for '{$row['ingredient']}' -- not in the file or this install, skipped.";
                    }
                    $wouldDelete = $wipe('inventory') ? PackageSize::where('quantity_on_hand', '!=', 0)->count() : 0;
                    break;

                case 'recipes':
                    $current = $wipe('recipes') ? [] : Recipe::pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
                    foreach ($rows as $row) {
                        in_array(mb_strtolower((string) ($row['name'] ?? '')), $current, true) ? $existing++ : $new++;
                        foreach ($row['lines'] ?? [] as $line) {
                            if (!isset($ingredientNames[mb_strtolower($line['ingredient'])])) {
                                $problems[] = "{$row['name']} uses '{$line['ingredient']}', which isn't in the file or this install -- that line will be skipped.";
                            }
                        }
                    }
                    $wouldDelete = $wipe('recipes') ? Recipe::count() : 0;
                    break;

                case 'production_runs':
                    // Runs aren't unique by name (rental-driven names repeat),
                    // so they match on name + date -- same as the seeder.
                    $current = $wipe('production_runs') ? [] : ProductionRun::get(['name', 'run_date'])
                        ->map(fn (ProductionRun $run) => $run->name.'|'.$run->run_date?->toDateString())->all();
                    foreach ($rows as $row) {
                        in_array(($row['name'] ?? '').'|'.($row['run_date'] ?? ''), $current, true) ? $existing++ : $new++;
                        foreach ($row['recipes'] ?? [] as $line) {
                            if (!isset($recipeNames[mb_strtolower($line['recipe'])])) {
                                $problems[] = "Run {$row['name']} makes '{$line['recipe']}', which isn't in the file or this install -- skipped.";
                            }
                        }
                    }
                    $wouldDelete = $wipe('production_runs') ? ProductionRun::count() : 0;
                    break;
            }

            $preview[$section] = [
                'rows' => count($rows),
                'new' => $new,
                'existing' => $existing,
                'would_delete' => $wouldDelete,
                'problems' => array_values(array_unique($problems)),
            ];
        }

        return $preview;
    }

    /**
     * @return array<string, array{created: int, updated: int, deleted: int, skipped: int}>
     */
    public function apply(array $document, array $sections, string $mode, ?int $actorId): array
    {
        $sections = $this->chosen($document, $sections);

        return DB::transaction(function () use ($document, $sections, $mode, $actorId) {
            $summary = array_fill_keys($sections, ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0]);

            if ($mode === 'wipe') {
                // Dependants first, so nothing is left pointing at a deleted row.
                foreach (array_reverse($sections) as $section) {
                    $summary[$section]['deleted'] = $this->wipe($section, $actorId);
                }
            }

            foreach ($sections as $section) {
                $summary[$section] = array_merge($summary[$section], match ($section) {
                    'ingredients' => $this->importIngredients($document['ingredients'], $actorId),
                    'price_history' => $this->importPriceHistory($document['price_history'], $actorId),
                    'inventory' => $this->importInventory($document['inventory'], $actorId),
                    'recipes' => $this->importRecipes($document['recipes'], $actorId),
                    'production_runs' => $this->importProductionRuns($document['production_runs'], $actorId),
                }, ['deleted' => $summary[$section]['deleted']]);
            }

            return $summary;
        });
    }

    private function chosen(array $document, array $sections): array
    {
        return array_values(array_intersect($this->sectionsIn($document), $sections));
    }

    /**
     * Lowercased ingredient and recipe names that will exist once the
     * chosen sections are in -- what references in the file can resolve to.
     */
    private function namesAfterImport(array $document, array $sections, string $mode): array
    {
        $wiping = fn (string $section) => $mode === 'wipe' && in_array($section, $sections, true);

        $ingredients = $wiping('ingredients') ? [] : Ingredient::pluck('name')->all();
        if (in_array('ingredients', $sections, true)) {
            $ingredients = array_merge($ingredients, array_column($document['ingredients'], 'name'));
        }

        $recipes = $wiping('recipes') ? [] : Recipe::pluck('name')->all();
        if (in_array('recipes', $sections, true)) {
            $recipes = array_merge($recipes, array_column($document['recipes'], 'name'));
        }

        $flip = fn (array $names) => array_flip(array_map(fn ($n) => mb_strtolower((string) $n), $names));

        return [$flip($ingredients), $flip($recipes)];
    }

    private function priceKey(?string $ingredient, ?string $date, ?string $provider, ?string $brand, mixed $qty, mixed $total): string
    {
        return implode('|', [mb_strtolower((string) $ingredient), $date, $provider, $brand, round((float) $qty, 2), round((float) $total, 2)]);
    }

    private function ingredientIds(): array
    {
        return Ingredient::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
    }

    private function wipe(string $section, ?int $actorId): int
    {
        switch ($section) {
            case 'production_runs':
                $runs = ProductionRun::all();
                $runs->each(function (ProductionRun $run) use ($actorId) {
                    $event = CostingRecordDeleted::forModel($run, $actorId, ['source' => 'import_wipe']);
                    $run->delete();
                    event($event);
                });

                return $runs->count();

            case 'recipes':
                $recipes = Recipe::all();
                $recipes->each(function (Recipe $recipe) use ($actorId) {
                    $event = CostingRecordDeleted::forModel($recipe, $actorId, ['source' => 'import_wipe']);
                    $recipe->delete();
                    event($event);
                });

                return $recipes->count();

            case 'inventory':
                $sources = PackageSize::where('quantity_on_hand', '!=', 0)->get();
                $sources->each(function (PackageSize $source) use ($actorId) {
                    $before = (float) $source->quantity_on_hand;
                    $source->update(['quantity_on_hand' => 0]);
                    $this->recordInventoryAdjustment->handle(packageSize: $source, reason: 'recount', onHandBefore: $before, onHandAfter: 0, notes: 'Cleared before import', userId: $actorId);
                });

                return $sources->count();

            case 'price_history':
                $count = PriceHistoryEntry::count();
                PriceHistoryEntry::query()->delete();
                event(new CostingRecordDeleted(
                    modelClass: PriceHistoryEntry::class,
                    modelId: 0,
                    label: "All price history ({$count} entries)",
                    attributes: ['count' => $count],
                    actorId: $actorId,
                    context: ['source' => 'import_wipe'],
                ));

                return $count;

            case 'ingredients':
                // Components first: a component can't be deleted while a
                // house-made ingredient still uses it.
                DB::table('costing_ingredient_components')->delete();
                $ingredients = Ingredient::all();
                $ingredients->each(function (Ingredient $ingredient) use ($actorId) {
                    $event = CostingRecordDeleted::forModel($ingredient, $actorId, ['source' => 'import_wipe']);
                    $ingredient->delete();
                    event($event);
                });

                return $ingredients->count();
        }

        return 0;
    }

    private function importIngredients(array $rows, ?int $actorId): array
    {
        $created = $updated = $skipped = 0;

        foreach ($rows as $row) {
            if (!in_array($row['unit_type'] ?? null, ['g', 'unit'], true) || trim((string) ($row['name'] ?? '')) === '') {
                $skipped++;
                continue;
            }

            $ingredient = Ingredient::whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])->first() ?? new Ingredient(['name' => $row['name']]);
            $isNew = !$ingredient->exists;

            $ingredient->fill([
                'category' => $row['category'] ?? null,
                'unit_type' => $row['unit_type'],
                'waste_percent' => $row['waste_percent'] ?? 100,
                'preferred_source' => $row['preferred_source'] ?? null,
                'preferred_brand' => $row['preferred_brand'] ?? null,
                'byproduct_name' => $row['byproduct_name'] ?? null,
                'notes' => $row['notes'] ?? null,
                'is_house_made' => (bool) ($row['is_house_made'] ?? false),
                'cook_down_percent' => $row['cook_down_percent'] ?? null,
            ]);
            $event = $isNew ? null : CostingRecordSaved::forUpdated($ingredient, $actorId, ['source' => 'import']);
            $ingredient->save();
            event($event ?? CostingRecordSaved::forCreated($ingredient, $actorId, ['source' => 'import']));
            $isNew ? $created++ : $updated++;

            // Sources: package sizes only -- stock comes from the inventory section.
            foreach ($row['sources'] ?? [] as $source) {
                PackageSize::updateOrCreate(
                    ['ingredient_id' => $ingredient->id, 'provider' => $source['provider'], 'brand' => $source['brand'] ?? null],
                    ['package_size' => $source['package_size'] ?? 1, 'units_per_case' => max(1, (int) ($source['units_per_case'] ?? 1))],
                );
            }
        }

        // Made-from lines once every ingredient exists, so they can refer
        // to ones further down the file.
        $ids = $this->ingredientIds();
        foreach ($rows as $row) {
            $id = $ids[mb_strtolower((string) ($row['name'] ?? ''))] ?? null;
            if ($id === null || !array_key_exists('made_from', $row)) {
                continue;
            }
            $sync = [];
            foreach ($row['made_from'] as $line) {
                $componentId = $ids[mb_strtolower($line['ingredient'])] ?? null;
                if ($componentId !== null && $componentId !== $id) {
                    $sync[$componentId] = ['quantity' => $line['quantity']];
                }
            }
            Ingredient::find($id)->components()->sync(!empty($row['is_house_made']) ? $sync : []);
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    private function importPriceHistory(array $rows, ?int $actorId): array
    {
        $ids = $this->ingredientIds();
        $existing = PriceHistoryEntry::with('ingredient:id,name')->get()
            ->map(fn (PriceHistoryEntry $entry) => $this->priceKey($entry->ingredient?->name, $entry->purchased_at?->toDateString(), $entry->provider, $entry->brand, $entry->qty, $entry->total_price))
            ->flip()->all();
        $created = $skipped = 0;

        foreach ($rows as $row) {
            $ingredientId = $ids[mb_strtolower((string) ($row['ingredient'] ?? ''))] ?? null;
            $key = $this->priceKey($row['ingredient'] ?? null, $row['purchased_at'] ?? null, $row['provider'] ?? null, $row['brand'] ?? null, $row['qty'] ?? null, $row['total_price'] ?? null);
            if ($ingredientId === null || isset($existing[$key])) {
                $skipped++;
                continue;
            }

            $entry = PriceHistoryEntry::create([
                'ingredient_id' => $ingredientId,
                'package_size_id' => PackageSize::where('ingredient_id', $ingredientId)
                    ->where('provider', $row['provider'] ?? null)->where('brand', $row['brand'] ?? null)->value('id'),
                'purchased_at' => $row['purchased_at'] ?? null,
                'provider' => $row['provider'] ?? null,
                'brand' => $row['brand'] ?? null,
                'qty' => $row['qty'] ?? null,
                'total_price' => $row['total_price'] ?? null,
                'priced_as_case' => (bool) ($row['priced_as_case'] ?? false),
                'sku' => $row['sku'] ?? null,
                'notes' => $row['notes'] ?? null,
            ]);
            // Not added to $existing: two identical purchases within the
            // file are both real; only ones already here are skipped.
            event(CostingRecordSaved::forCreated($entry, $actorId, ['source' => 'import']));
            $created++;
        }

        return ['created' => $created, 'updated' => 0, 'skipped' => $skipped];
    }

    /** Sets each source's stock to the file's figure, logged as a recount. */
    private function importInventory(array $rows, ?int $actorId): array
    {
        $ids = $this->ingredientIds();
        $updated = $skipped = 0;

        foreach ($rows as $row) {
            $ingredientId = $ids[mb_strtolower((string) ($row['ingredient'] ?? ''))] ?? null;
            $source = $ingredientId === null ? null : PackageSize::where('ingredient_id', $ingredientId)
                ->where('provider', $row['provider'] ?? null)->where('brand', $row['brand'] ?? null)->first();
            if ($source === null) {
                $skipped++;
                continue;
            }

            $before = (float) $source->quantity_on_hand;
            $after = (float) ($row['quantity_on_hand'] ?? 0);
            if ($before !== $after) {
                $source->update(['quantity_on_hand' => $after]);
                $this->recordInventoryAdjustment->handle(packageSize: $source, reason: 'recount', onHandBefore: $before, onHandAfter: $after, notes: 'Imported from file', userId: $actorId);
                $updated++;
            }
        }

        return ['created' => 0, 'updated' => $updated, 'skipped' => $skipped];
    }

    private function importRecipes(array $rows, ?int $actorId): array
    {
        $ids = $this->ingredientIds();
        $created = $updated = $skipped = 0;

        foreach ($rows as $row) {
            if (trim((string) ($row['name'] ?? '')) === '') {
                $skipped++;
                continue;
            }

            $recipe = Recipe::whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])->first() ?? new Recipe(['name' => $row['name']]);
            $isNew = !$recipe->exists;

            $recipe->fill([
                'notes' => $row['notes'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sell_price' => $row['sell_price'] ?? null,
                'fill_size_g' => $row['fill_size_g'] ?? null,
                'preferred_batch_size' => $row['preferred_batch_size'] ?? null,
                'cost_buffer_percent' => $row['cost_buffer_percent'] ?? null,
                'min_stock_threshold' => $row['min_stock_threshold'] ?? null,
            ]);
            $event = $isNew ? null : CostingRecordSaved::forUpdated($recipe, $actorId, ['source' => 'import']);
            $recipe->save();

            $sync = [];
            foreach ($row['lines'] ?? [] as $line) {
                $ingredientId = $ids[mb_strtolower($line['ingredient'])] ?? null;
                if ($ingredientId !== null) {
                    $sync[$ingredientId] = ['quantity_per_jar' => $line['quantity'], 'is_byproduct' => (bool) ($line['byproduct'] ?? false)];
                }
            }
            $recipe->ingredients()->sync($sync);

            event($event ?? CostingRecordSaved::forCreated($recipe, $actorId, ['source' => 'import']));
            $isNew ? $created++ : $updated++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /** Runs come in as they were -- completed ones as records, with no stock deducted. */
    private function importProductionRuns(array $rows, ?int $actorId): array
    {
        $recipeIds = Recipe::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
        $created = $updated = 0;

        foreach ($rows as $row) {
            // Matched on name + date: run names repeat (they follow the rental booking).
            $run = ProductionRun::where('name', $row['name'] ?? null)->whereDate('run_date', $row['run_date'] ?? now()->toDateString())->first() ?? new ProductionRun;
            $isNew = !$run->exists;

            $run->fill([
                'name' => $row['name'] ?? null,
                'type' => in_array($row['type'] ?? null, ProductionRun::TYPES, true) ? $row['type'] : 'production',
                'run_date' => $row['run_date'] ?? now()->toDateString(),
                'batch_size' => max(1, (int) ($row['batch_size'] ?? 1)),
                'notes' => $row['notes'] ?? null,
                'completed_at' => $row['completed_at'] ?? null,
                'purchase_order_record' => $row['purchase_order_record'] ?? null,
                'recipe_sheet_record' => $row['recipe_sheet_record'] ?? null,
            ]);
            $event = $isNew ? null : CostingRecordSaved::forUpdated($run, $actorId, ['source' => 'import']);
            $run->save();

            $sync = [];
            foreach ($row['recipes'] ?? [] as $line) {
                $recipeId = $recipeIds[mb_strtolower($line['recipe'])] ?? null;
                if ($recipeId !== null) {
                    $sync[$recipeId] = ['batches' => (int) ($line['batches'] ?? 0), 'actual_units' => $line['actual_units'] ?? null];
                }
            }
            $run->recipes()->sync($sync);

            event($event ?? CostingRecordSaved::forCreated($run, $actorId, ['source' => 'import']));
            $isNew ? $created++ : $updated++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => 0];
    }
}
