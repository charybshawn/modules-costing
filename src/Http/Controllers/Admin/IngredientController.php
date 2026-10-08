<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\CalculateIngredientCosting;
use Cultpantry\Costing\Actions\GetIngredientPriceOptions;
use Cultpantry\Costing\Actions\GetPriceStalenessDays;
use Cultpantry\Costing\Events\CostingRecordDeleted;
use Cultpantry\Costing\Events\CostingRecordSaved;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller implements HasMiddleware
{
    /**
     * Three layers of protection, plus a fourth applied here to every
     * action (not just index): the admin Settings -> Modules enable
     * toggle. Putting it in middleware() rather than repeating
     * abort_unless(...) in each method means a disabled module is
     * actually blocked on every route (create/store/edit/update/destroy),
     * not just the index page.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless($request->user()?->canAccessAdminPanel(), 403, 'Admin access required.');
                return $next($request);
            }),
            new Middleware(function ($request, $next) {
                abort_unless(app(GetSiteSetting::class)->handle('modules.cultpantry/costing.enabled', true), 404);
                return $next($request);
            }),
        ];
    }

    public function index(CalculateIngredientCosting $calculateIngredientCosting): Response
    {
        $this->authorize('viewAny', Ingredient::class);

        // 'priceHistory.ingredient' looks redundant (we already have the
        // parent Ingredient) but is required: PriceHistoryEntry::price_per_unit
        // reads $this->ingredient to decide g->kg scaling, and without this
        // eager load that lazy-loads per entry, which throws under this
        // app's Model::preventLazyLoading() outside production. 'inventory'
        // and 'packageSizes' are required too -- CalculateIngredientCosting
        // reads unit_size and per-brand package sizes off them for
        // purchase_size/purchase_unit. 'recipes:id,name' drives the
        // Recipe filter below -- an ingredient can be in several recipes
        // (belongsToMany), so it's exposed as recipe_ids rather than a
        // single scalar column.
        $ingredients = Ingredient::with('priceHistory.ingredient', 'inventory', 'packageSizes', 'recipes:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $ingredient) => array_merge(
                [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'category' => $ingredient->category,
                    'unit_type' => $ingredient->unit_type,
                    'waste_percent' => (float) $ingredient->waste_percent,
                    'notes' => $ingredient->notes,
                    'byproduct_name' => $ingredient->byproduct_name,
                    'source_count' => $ingredient->packageSizes->count(),
                    'is_house_made' => $ingredient->is_house_made,
                    'recipe_ids' => $ingredient->recipes->pluck('id')->all(),
                ],
                $calculateIngredientCosting->handle($ingredient)
            ));

        return Inertia::render('Vendor/costing/Ingredients/Index', [
            'ingredients' => $ingredients,
            'recipes' => Recipe::orderBy('name')->get(['id', 'name']),
            'staleness_days' => app(GetPriceStalenessDays::class)->handle(),
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Ingredients']),
        ]);
    }

    public function create(CalculateIngredientCosting $calculateIngredientCosting): Response
    {
        $this->authorize('create', Ingredient::class);

        return Inertia::render('Vendor/costing/Ingredients/Create', [
            'categories' => $this->knownCategories(),
            'componentPool' => $this->componentPool(null, $calculateIngredientCosting),
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Ingredients', 'href' => route('admin.costing.ingredients.index')],
                ['label' => 'Add Ingredient'],
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Ingredient::class);

        $validated = $this->validated($request);

        $ingredient = Ingredient::create(Arr::except($validated, 'components'));
        $this->syncComponents($ingredient, $validated);

        event(CostingRecordSaved::forCreated($ingredient, auth()->id()));

        // Background autosave from the Create form: the first save creates
        // the ingredient and hands off to its Edit page (same step, and
        // `created=1` so that page's × offers to discard it outright). No
        // success flash -- the page's SaveIndicator is the feedback.
        if ($request->boolean('stay')) {
            return redirect()->route('admin.costing.ingredients.edit', [
                'ingredient' => $ingredient,
                'step' => max(0, $request->integer('step')),
                'created' => 1,
            ]);
        }

        return redirect()
            ->route('admin.costing.ingredients.index')
            ->with('success', "Ingredient '{$ingredient->name}' created.");
    }

    /**
     * Read-only view -- where the Ingredients list's rows land (edit is
     * one tap away), per the host's show-page pattern.
     */
    public function show(Ingredient $ingredient, CalculateIngredientCosting $calculateIngredientCosting): Response
    {
        $this->authorize('view', $ingredient);

        // Same eager loads as index() -- CalculateIngredientCosting reads
        // priceHistory (with its ingredient, for price_per_unit), inventory
        // and packageSizes, and lazy loading throws outside production.
        $ingredient->load('priceHistory.ingredient', 'inventory', 'packageSizes', 'recipes:id,name,is_active', 'usedInHouseMade:id,name');

        return Inertia::render('Vendor/costing/Ingredients/Show', [
            'ingredient' => array_merge(
                [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'category' => $ingredient->category,
                    'unit_type' => $ingredient->unit_type,
                    'waste_percent' => (float) $ingredient->waste_percent,
                    'notes' => $ingredient->notes,
                    'byproduct_name' => $ingredient->byproduct_name,
                    'is_house_made' => $ingredient->is_house_made,
                    'cook_down_percent' => $ingredient->cook_down_percent !== null ? (float) $ingredient->cook_down_percent : null,
                    'batch_input_g' => $ingredient->is_house_made ? $ingredient->batchInputGrams() : null,
                    'yield_g' => $ingredient->yieldGrams(),
                    // Made-from lines, each with its own costing, for the
                    // breakdown table (house-made only).
                    'components' => $ingredient->is_house_made
                        ? $ingredient->components()->with('priceHistory.ingredient', 'inventory', 'packageSizes')->orderBy('name')->get()
                            ->map(fn (Ingredient $component) => array_merge(
                                [
                                    'id' => $component->id,
                                    'name' => $component->name,
                                    'unit_type' => $component->unit_type,
                                    'is_house_made' => $component->is_house_made,
                                    'quantity' => (float) $component->pivot->quantity,
                                ],
                                $calculateIngredientCosting->handle($component)
                            ))->values()
                        : [],
                    'used_in_house_made' => $ingredient->usedInHouseMade->sortBy('name')->map(fn (Ingredient $parent) => [
                        'id' => $parent->id,
                        'name' => $parent->name,
                    ])->values(),
                    'recipes' => $ingredient->recipes->sortBy('name')->map(fn (Recipe $recipe) => [
                        'id' => $recipe->id,
                        'name' => $recipe->name,
                        'is_active' => $recipe->is_active,
                    ])->values(),
                ],
                $calculateIngredientCosting->handle($ingredient)
            ),
            'staleness_days' => app(GetPriceStalenessDays::class)->handle(),
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Ingredients', 'href' => route('admin.costing.ingredients.index')],
                ['label' => $ingredient->name],
            ),
        ]);
    }

    public function edit(Ingredient $ingredient, CalculateIngredientCosting $calculateIngredientCosting): Response
    {
        $this->authorize('update', $ingredient);

        $ingredient->load('components');

        return Inertia::render('Vendor/costing/Ingredients/Edit', [
            'ingredient' => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'category' => $ingredient->category,
                'unit_type' => $ingredient->unit_type,
                'waste_percent' => (float) $ingredient->waste_percent,
                'notes' => $ingredient->notes,
                'byproduct_name' => $ingredient->byproduct_name,
                'is_house_made' => $ingredient->is_house_made,
                'cook_down_percent' => $ingredient->cook_down_percent !== null ? (float) $ingredient->cook_down_percent : null,
                'components' => $ingredient->components->map(fn (Ingredient $component) => [
                    'ingredient_id' => $component->id,
                    'quantity_per_jar' => (float) $component->pivot->quantity,
                ])->values(),
            ],
            'categories' => $this->knownCategories(),
            'componentPool' => $this->componentPool($ingredient, $calculateIngredientCosting),
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Ingredients', 'href' => route('admin.costing.ingredients.index')],
                ['label' => $ingredient->name],
            ),
        ]);
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('update', $ingredient);

        $validated = $this->validated($request, $ingredient->id);

        $ingredient->fill(Arr::except($validated, 'components'));
        $savedEvent = CostingRecordSaved::forUpdated($ingredient, auth()->id());
        $ingredient->save();
        $this->syncComponents($ingredient, $validated);
        event($savedEvent);

        // Background autosave from the Edit page: stay on it, no success
        // flash (SaveIndicator is the feedback).
        if ($request->boolean('stay')) {
            return redirect()->route('admin.costing.ingredients.edit', $ingredient);
        }

        return redirect()
            ->route('admin.costing.ingredients.index')
            ->with('success', "Ingredient '{$ingredient->name}' updated.");
    }

    /**
     * Copies an ingredient's details -- and, for a house-made one, what it's
     * made from -- into a new ingredient named "<name> 2" (or the next free
     * number), then opens it for editing. Sources, prices, stock and the
     * preferred source stay with the original: they're facts about what was
     * actually bought, not about the ingredient's definition.
     */
    public function duplicate(Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('view', $ingredient);
        $this->authorize('create', Ingredient::class);

        $ingredient->load('components');

        $copy = $ingredient->replicate(['preferred_source', 'preferred_brand']);
        $copy->name = $this->nextCopyName($ingredient->name);
        $copy->save();

        $copy->components()->sync($ingredient->components->mapWithKeys(fn (Ingredient $component) => [
            $component->id => ['quantity' => $component->pivot->quantity],
        ])->all());

        event(CostingRecordSaved::forCreated($copy, auth()->id(), ['duplicated_from' => $ingredient->id]));

        return redirect()
            ->route('admin.costing.ingredients.edit', $copy)
            ->with('success', "Duplicated '{$ingredient->name}' as '{$copy->name}'.");
    }

    public function priceOptions(Ingredient $ingredient, GetIngredientPriceOptions $getIngredientPriceOptions): JsonResponse
    {
        $this->authorize('view', $ingredient);

        return response()->json([
            'options' => $getIngredientPriceOptions->handle($ingredient),
        ]);
    }

    public function setPreferred(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('update', $ingredient);

        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
        ]);

        $ingredient->fill([
            'preferred_source' => $validated['provider'] ?? null,
            'preferred_brand' => $validated['brand'] ?? null,
        ]);
        $savedEvent = CostingRecordSaved::forUpdated($ingredient, auth()->id());
        $ingredient->save();
        event($savedEvent);

        // Not a hardcoded route -- the Available Prices modal that calls
        // this is reused from both the Ingredients page and the Recipe
        // Edit page, and should reload whichever one the request came from.
        return redirect()->back()->with('success', $validated['provider']
            ? "Preferred source for '{$ingredient->name}' set to {$validated['provider']}."
            : "Preferred source for '{$ingredient->name}' cleared -- back to auto-cheapest.");
    }

    /**
     * Records the real minimum purchase package size for one provider/brand
     * combination (e.g. "Cream Cheese from GFS/Kraft comes in 20kg
     * blocks") -- CalculateIngredientCosting uses this instead of the
     * ingredient-level InventoryItem.unit_size whenever that exact
     * provider/brand wins the current price. One row per provider/brand,
     * upserted rather than duplicated on repeat edits.
     */
    public function setPackageSize(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('update', $ingredient);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'package_size' => ['required', 'numeric', 'min:0.01'],
            // Required, not nullable/defaulted here -- every caller must
            // explicitly send a value (1 when not sold by the case).
            // updateOrCreate's update array only contains what's passed,
            // so making this optional would let a package-size-only edit
            // silently reset a previously-set case size back to 1.
            'units_per_case' => ['required', 'integer', 'min:1'],
        ]);

        // firstOrNew + save rather than updateOrCreate, so the audit event
        // can capture the old values before they're overwritten.
        $packageSize = PackageSize::firstOrNew([
            'ingredient_id' => $ingredient->id,
            'provider' => $validated['provider'],
            'brand' => $validated['brand'] ?? null,
        ]);
        $packageSize->fill(['package_size' => $validated['package_size'], 'units_per_case' => $validated['units_per_case']]);
        $savedEvent = $packageSize->exists ? CostingRecordSaved::forUpdated($packageSize, auth()->id()) : null;
        $packageSize->save();
        event($savedEvent ?? CostingRecordSaved::forCreated($packageSize, auth()->id()));

        // Editing an existing source's package size leaves its most
        // recently logged price's qty pointing at whatever size was true
        // when that price was logged -- price_per_unit (total / qty) then
        // silently reflects the OLD size forever, since nothing else
        // re-derives it. Refresh just that one entry's qty to the new
        // size, same convention updatePrice()'s quick re-log already uses
        // (see PriceHistoryController::withSourceSnapshot) -- the dollar
        // amount actually paid is untouched, only the size it's divided by
        // is corrected. That entry's own priced_as_case decides whether
        // "the new size" means package_size or case_total: units_per_case
        // is a purchasing constraint on the Source, but whether a given
        // logged price was for one package or the whole case is a property
        // of that price entry, not of the Source. Skipped for a brand-new
        // source: it has no price history yet to refresh.
        if (!$packageSize->wasRecentlyCreated) {
            $latestEntry = PriceHistoryEntry::where('package_size_id', $packageSize->id)
                ->orderByDesc('purchased_at')
                ->orderByDesc('id')
                ->first();

            if ($latestEntry) {
                $latestEntry->fill(['qty' => $latestEntry->priced_as_case ? $packageSize->case_total : $packageSize->package_size]);
                $entryEvent = CostingRecordSaved::forUpdated($latestEntry, auth()->id(), ['reason' => 'package_size_changed']);
                $latestEntry->save();
                event($entryEvent);
            }
        }

        // Not a hardcoded route -- same reasoning as setPreferred() above,
        // this is called from the same reused Available Prices modal.
        return redirect()->back()->with('success', "Package size for '{$ingredient->name}' ({$validated['provider']}) updated.");
    }

    /**
     * Renames an existing source's provider/brand in place -- a typo fix or
     * "GFS" -> "GFS Foodservice" rename, not a new source. Distinct from
     * setPackageSize()'s updateOrCreate, which is keyed BY provider/brand
     * and would treat a changed name as a brand-new row rather than editing
     * this one.
     *
     * Cascades to every linked PriceHistoryEntry's own provider/brand too,
     * not just this PackageSize row. Those columns are a snapshot (see
     * PriceHistoryEntry's docblock) so history survives the source being
     * deleted, but as long as package_size_id still links back here, they
     * need to track the live name -- CalculateIngredientCosting's preferred-
     * source filter and GetIngredientPriceOptions's Sources table both match
     * against these snapshots, and leaving them on the old name would make
     * every price logged before the rename invisible to both (a renamed
     * preferred source would silently look like it has no recent price at
     * all). InventoryAdjustment's own source_provider/source_brand
     * snapshot is deliberately left untouched by contrast -- nothing reads
     * it for live matching, only for display, so there's no equivalent
     * breakage to guard against there.
     */
    public function renameSource(Request $request, Ingredient $ingredient, PackageSize $packageSize): RedirectResponse
    {
        $this->authorize('update', $ingredient);
        abort_unless($packageSize->ingredient_id === $ingredient->id, 404);

        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
        ]);
        $brand = $validated['brand'] ?? null;

        $duplicate = PackageSize::where('ingredient_id', $ingredient->id)
            ->where('id', '!=', $packageSize->id)
            ->where('provider', $validated['provider'])
            ->where('brand', $brand)
            ->exists();

        abort_if($duplicate, 422, "'{$ingredient->name}' already has a source with that provider/brand.");

        // preferred_source/preferred_brand match a source by exact live
        // string (see GetIngredientPriceOptions::isPreferred()), not by
        // package_size_id -- renaming the currently-preferred source would
        // otherwise silently break its "preferred" status.
        $wasPreferred = $ingredient->preferred_source === $packageSize->provider
            && $ingredient->preferred_brand === $packageSize->brand;

        $packageSize->fill(['provider' => $validated['provider'], 'brand' => $brand]);
        $sourceEvent = CostingRecordSaved::forUpdated($packageSize, auth()->id());
        $packageSize->save();

        $renamedEntries = PriceHistoryEntry::where('package_size_id', $packageSize->id)
            ->update(['provider' => $validated['provider'], 'brand' => $brand]);

        // The source's event carries the bulk-renamed price entries as context.
        event(new CostingRecordSaved(
            modelClass: $sourceEvent->modelClass,
            modelId: $sourceEvent->modelId,
            action: $sourceEvent->action,
            label: $sourceEvent->label,
            changes: $sourceEvent->changes,
            actorId: $sourceEvent->actorId,
            context: ['price_entries_renamed' => $renamedEntries],
        ));

        if ($wasPreferred) {
            $ingredient->fill(['preferred_source' => $validated['provider'], 'preferred_brand' => $brand]);
            $preferredEvent = CostingRecordSaved::forUpdated($ingredient, auth()->id(), ['reason' => 'preferred_source_renamed']);
            $ingredient->save();
            event($preferredEvent);
        }

        return redirect()->back()->with('success', "Source renamed to '{$validated['provider']}'.");
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('delete', $ingredient);

        if ($ingredient->usedInHouseMade()->exists()) {
            $parents = $ingredient->usedInHouseMade()->orderBy('name')->pluck('name')->implode(', ');

            return redirect()->back()->with('error', "'{$ingredient->name}' is used to make {$parents} -- remove it from there first.");
        }

        $name = $ingredient->name;
        $deletedEvent = CostingRecordDeleted::forModel($ingredient, auth()->id());
        $ingredient->delete();
        event($deletedEvent);

        return redirect()
            ->route('admin.costing.ingredients.index')
            ->with('success', "Ingredient '{$name}' deleted.");
    }

    /**
     * Hard-deletes an ingredient the Create form autosaved into existence
     * and the admin then discarded (Edit page × -> "Discard Ingredient").
     * IngredientPolicy::discardDraft limits it to fresh ingredients nothing
     * depends on yet.
     */
    public function discardDraft(Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('discardDraft', $ingredient);

        $deletedEvent = CostingRecordDeleted::forModel($ingredient, auth()->id(), ['discarded_draft' => true]);
        $ingredient->delete();
        event($deletedEvent);

        return redirect()
            ->route('admin.costing.ingredients.index')
            ->with('success', 'Ingredient discarded.');
    }

    /**
     * Multi-select "Delete" from the Ingredients table -- same per-row
     * destroy() above, just looped with a per-item try/catch so one
     * unexpected failure (e.g. a stale id) doesn't abort the rest of the
     * batch, same success/fail tally pattern as the host app's
     * CategoryController::bulkAction().
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:costing_ingredients,id'],
        ]);

        $successCount = 0;
        $failCount = 0;

        foreach ($validated['ids'] as $id) {
            try {
                $ingredient = Ingredient::findOrFail($id);
                $this->authorize('delete', $ingredient);
                $deletedEvent = CostingRecordDeleted::forModel($ingredient, auth()->id(), ['source' => 'bulk_action']);
                $ingredient->delete();
                event($deletedEvent);
                $successCount++;
            } catch (\Throwable) {
                $failCount++;
            }
        }

        $message = "Deleted {$successCount} ingredient".($successCount === 1 ? '' : 's').'.';
        if ($failCount > 0) {
            $message .= " {$failCount} could not be deleted.";
        }

        return redirect()->back()->with($failCount === 0 ? 'success' : 'warning', $message);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('costing_ingredients', 'name')->ignore($ignoreId),
            ],
            'category' => ['nullable', 'string', 'max:255'],
            'unit_type' => ['required', Rule::in(['g', 'unit'])],
            'waste_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string'],
            'byproduct_name' => ['nullable', 'string', 'max:100'],
            'is_house_made' => ['boolean'],
            // Not required: autosave fires as soon as the toggle flips, before
            // there's a figure to enter. Without one it's simply unpriced.
            // Over 100 is allowed (unlisted water can go in).
            'cook_down_percent' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'components' => ['nullable', 'array'],
            'components.*.ingredient_id' => [
                'required', 'distinct', 'exists:costing_ingredients,id',
                Rule::notIn(array_filter([$ignoreId])),
            ],
            'components.*.quantity_per_jar' => ['required', 'numeric', 'min:0'],
        ], [
            'components.*.ingredient_id.not_in' => 'An ingredient can\'t be made from itself.',
        ]);

        if (!empty($validated['is_house_made'])) {
            $errors = [];
            if ($validated['unit_type'] !== 'g') {
                $errors['unit_type'] = 'A house-made ingredient is measured in grams.';
            }
            $componentIds = array_map('intval', array_column($validated['components'] ?? [], 'ingredient_id'));
            if ($ignoreId !== null && $this->reaches($componentIds, $ignoreId)) {
                $errors['components'] = 'That would make this ingredient part of its own recipe.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        return $validated;
    }

    /**
     * Saves a house-made ingredient's made-from lines (zero quantities are
     * dropped, like recipe lines); switching it off clears them.
     */
    private function syncComponents(Ingredient $ingredient, array $validated): void
    {
        if (!array_key_exists('is_house_made', $validated) && !array_key_exists('components', $validated)) {
            return;
        }

        $sync = [];
        if ($ingredient->is_house_made) {
            foreach ($validated['components'] ?? [] as $row) {
                if ((float) $row['quantity_per_jar'] > 0) {
                    $sync[$row['ingredient_id']] = ['quantity' => $row['quantity_per_jar']];
                }
            }
        }

        $ingredient->components()->sync($sync);
    }

    /**
     * Whether $targetId is among $startIds or anything they're (transitively)
     * made from -- i.e. adding $startIds as components of $targetId would
     * create a loop.
     */
    private function reaches(array $startIds, int $targetId): bool
    {
        $seen = [];
        $queue = $startIds;

        while ($queue) {
            $id = array_shift($queue);
            if ($id === $targetId) {
                return true;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            array_push($queue, ...DB::table('costing_ingredient_components')
                ->where('ingredient_id', $id)
                ->pluck('component_ingredient_id')
                ->map(fn ($componentId) => (int) $componentId)
                ->all());
        }

        return false;
    }

    /**
     * Every ingredient a house-made one could be made from, with costing for
     * the form's live cost readout -- all except the one being edited.
     */
    private function componentPool(?Ingredient $exclude, CalculateIngredientCosting $calculateIngredientCosting): Collection
    {
        return Ingredient::with('priceHistory.ingredient', 'inventory', 'packageSizes')
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude->id))
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $ingredient) => array_merge(
                [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'unit_type' => $ingredient->unit_type,
                    'byproduct_name' => $ingredient->byproduct_name,
                ],
                $calculateIngredientCosting->handle($ingredient)
            ));
    }

    /**
     * "Apple Butter" -> "Apple Butter 2"; "Apple Butter 2" -> "Apple Butter 3"
     * -- the first number not already taken by another ingredient.
     */
    private function nextCopyName(string $name): string
    {
        $base = preg_replace('/ \d+$/', '', $name);
        $number = 2;
        while (Ingredient::where('name', "{$base} {$number}")->exists()) {
            $number++;
        }

        return "{$base} {$number}";
    }

    /**
     * Categories already used by other ingredients -- offered as
     * autocomplete suggestions rather than a fixed list, so a new one can
     * always just be typed without any extra "add category" step.
     */
    private function knownCategories(): Collection
    {
        return Ingredient::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }
}
