<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\BuildRecipeSheet;
use Cultpantry\Costing\Actions\CalculateIngredientCosting;
use Cultpantry\Costing\Actions\CalculateMaxProducibleUnits;
use Cultpantry\Costing\Actions\CalculateRecipeCost;
use Cultpantry\Costing\Contracts\FinishedGoodRepository;
use Cultpantry\Costing\Events\CostingRecordDeleted;
use Cultpantry\Costing\Events\CostingRecordSaved;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Models\RecipeCostSnapshot;
use Cultpantry\Costing\Models\RecipeSize;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller implements HasMiddleware
{
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

    public function index(CalculateMaxProducibleUnits $calculateMaxProducibleUnits): Response
    {
        $this->authorize('viewAny', Recipe::class);

        $recipes = Recipe::withCount('ingredients')
            ->with('mainIngredients.inventory')
            ->orderBy('name')
            ->get(['id', 'name', 'notes', 'min_stock_threshold', 'is_active'])
            ->map(fn (Recipe $recipe) => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'notes' => $recipe->notes,
                'ingredients_count' => $recipe->ingredients_count,
                'min_stock_threshold' => $recipe->min_stock_threshold,
                'is_active' => $recipe->is_active,
                'max_producible_units' => $calculateMaxProducibleUnits->handle($recipe),
            ]);

        return Inertia::render('Vendor/costing/Recipes/Index', [
            'recipes' => $recipes,
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Recipes']),
        ]);
    }

    /**
     * Side-by-side comparison of every recipe's ingredient quantities --
     * the sheet's Recipes tab showed all flavours as columns at once; the
     * per-recipe edit form here only shows one at a time, so this is a
     * read-only view to compare them (editing still happens on edit()).
     */
    public function grid(): Response
    {
        $this->authorize('viewAny', Recipe::class);

        $ingredients = Ingredient::orderBy('name')->get(['id', 'name', 'unit_type', 'category']);
        $recipes = Recipe::with('mainIngredients')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Vendor/costing/Recipes/Grid', [
            'ingredients' => $ingredients,
            'recipes' => $recipes->map(fn (Recipe $recipe) => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'quantities' => $recipe->mainIngredients->mapWithKeys(
                    fn (Ingredient $ingredient) => [$ingredient->id => (float) $ingredient->pivot->quantity_per_jar]
                ),
            ]),
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Recipes', 'href' => route('admin.costing.recipes.index')],
                ['label' => 'Grid View'],
            ),
        ]);
    }

    /**
     * Food Cost % across every recipe at once -- the authoritative,
     * sell-price-aware costing dashboard. Distinct from the lightweight
     * "Estimated cost per jar" shown on edit() (that's quick feedback
     * while tweaking ingredient quantities; this is the real per-flavour
     * costing view, reusing the same CalculateIngredientCosting under the
     * hood via CalculateRecipeCost).
     */
    public function costing(CalculateRecipeCost $calculateRecipeCost): Response
    {
        $this->authorize('viewAny', Recipe::class);

        // 'mainIngredients.priceHistory.ingredient' required: same
        // LazyLoadingViolationException reasoning as edit() above,
        // CalculateRecipeCost -> CalculateIngredientCosting reads
        // $entry->ingredient per price history row. 'mainIngredients.inventory'
        // and 'mainIngredients.packageSizes' avoid the same N+1 for
        // purchase_size/purchase_unit.
        $recipes = Recipe::with('mainIngredients.priceHistory.ingredient', 'mainIngredients.inventory', 'mainIngredients.packageSizes', 'sizes')->orderBy('name')->get();

        // One row per size -- each has its own fill weight, packaging and
        // price (read from its linked product, so not editable here). A
        // recipe with no sizes yet still gets one row, costed as the bare mix.
        $rows = $recipes->flatMap(function (Recipe $recipe) use ($calculateRecipeCost) {
            $base = [
                'recipe_id' => $recipe->id,
                'cost_buffer_percent' => $recipe->cost_buffer_percent !== null ? (float) $recipe->cost_buffer_percent : null,
            ];

            if ($recipe->sizes->isEmpty()) {
                return [array_merge($base, ['id' => "r{$recipe->id}", 'name' => $recipe->name, 'size_label' => null], $calculateRecipeCost->handle($recipe))];
            }

            return $recipe->sizes->map(fn (RecipeSize $size) => array_merge(
                $base,
                ['id' => "s{$size->id}", 'name' => $recipe->name, 'size_label' => $size->label],
                $calculateRecipeCost->handle($recipe, $size),
            ));
        })->values();

        return Inertia::render('Vendor/costing/Recipes/Costing', [
            'recipes' => $rows,
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Recipes', 'href' => route('admin.costing.recipes.index')],
                ['label' => 'Costing'],
            ),
        ]);
    }

    /**
     * All recorded cost history, across every recipe, for the Cost History
     * chart -- snapshots are only ever created automatically when a
     * Production Run completes (see CompleteProductionRun /
     * CreateRecipeCostSnapshot), never here. Loads the full set and lets
     * the page filter/bucket client-side; snapshot volume is inherently
     * small (one row per recipe per completed run), so this is simpler
     * and more responsive than a query-param round-trip per filter change.
     */
    public function costHistory(): Response
    {
        $this->authorize('viewAny', Recipe::class);

        $snapshots = RecipeCostSnapshot::with('recipe:id,name', 'recipeSize:id,label')
            ->orderBy('created_at')
            ->get()
            ->map(fn (RecipeCostSnapshot $snapshot) => [
                'id' => $snapshot->id,
                'recipe_id' => $snapshot->recipe_id,
                'recipe_name' => $snapshot->recipe?->name,
                'recipe_size_id' => $snapshot->recipe_size_id,
                'size_label' => $snapshot->recipeSize?->label,
                'jars_produced' => $snapshot->jars_produced,
                'created_at' => $snapshot->created_at->toIso8601String(),
                'raw_cost' => (float) $snapshot->raw_cost,
                'buffered_cost' => (float) $snapshot->buffered_cost,
                'actual_cost_per_jar' => (float) $snapshot->actual_cost_per_jar,
                'food_cost_percent' => $snapshot->food_cost_percent !== null ? (float) $snapshot->food_cost_percent : null,
                'any_stale' => $snapshot->any_stale,
                'any_missing' => $snapshot->any_missing,
            ]);

        return Inertia::render('Vendor/costing/Recipes/CostHistory', [
            'recipes' => Recipe::orderBy('name')->get(['id', 'name']),
            'snapshots' => $snapshots,
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Recipes', 'href' => route('admin.costing.recipes.index')],
                ['label' => 'Cost History'],
            ),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Recipe::class);

        return Inertia::render('Vendor/costing/Recipes/Create', [
            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name', 'unit_type', 'byproduct_name']),
            'packagingIngredients' => $this->packagingIngredients(),
            'existingRecipeNames' => Recipe::orderBy('name')->pluck('name'),
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Recipes', 'href' => route('admin.costing.recipes.index')],
                ['label' => 'Add Recipe'],
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Recipe::class);

        $validated = $this->validated($request);

        $recipe = Recipe::create([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'min_stock_threshold' => $validated['min_stock_threshold'] ?? null,
            'preferred_batch_size' => $validated['preferred_batch_size'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $recipe->mainIngredients()->sync($this->syncData($validated['ingredients']));
        $recipe->byproductIngredients()->sync($this->syncData($validated['byproducts'] ?? []));
        $this->syncSizes($recipe, $validated['sizes'] ?? []);

        event(CostingRecordSaved::forCreated($recipe, auth()->id()));

        // Background autosave from the Create form: hand off to the new
        // recipe's Edit page (same step; `created=1` lets its × discard the
        // recipe outright). No success flash.
        if ($request->boolean('stay')) {
            return redirect()->route('admin.costing.recipes.edit', [
                'recipe' => $recipe,
                'step' => max(0, $request->integer('step')),
                'created' => 1,
            ]);
        }

        return redirect()
            ->route('admin.costing.recipes.index')
            ->with('success', "Recipe '{$recipe->name}' created.");
    }

    /**
     * Read-only view -- where the Recipes list's rows land (edit is one
     * tap away), per the host's show-page pattern.
     */
    public function show(
        Recipe $recipe,
        CalculateIngredientCosting $calculateIngredientCosting,
        CalculateMaxProducibleUnits $calculateMaxProducibleUnits,
        CalculateRecipeCost $calculateRecipeCost,
        BuildRecipeSheet $buildRecipeSheet,
    ): Response {
        $this->authorize('view', $recipe);

        // 'mainIngredients.inventory' for CalculateMaxProducibleUnits; the
        // costing loads below for CalculateIngredientCosting (see edit()).
        $recipe->load(
            'mainIngredients.priceHistory.ingredient',
            'mainIngredients.inventory',
            'mainIngredients.packageSizes',
            'byproductIngredients',
            'sizes.packaging',
        );

        return Inertia::render('Vendor/costing/Recipes/Show', [
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'notes' => $recipe->notes,
                'min_stock_threshold' => $recipe->min_stock_threshold,
                'preferred_batch_size' => $recipe->preferred_batch_size,
                'is_active' => $recipe->is_active,
                'sizes' => $recipe->sizes->map(function (RecipeSize $size) use ($recipe, $calculateRecipeCost) {
                    $cost = $calculateRecipeCost->handle($recipe, $size);

                    return [
                        'id' => $size->id,
                        'label' => $size->label,
                        'fill_size_g' => (float) $size->fill_size_g,
                        'finished_good' => $this->finishedGoodOption($size->finished_good_id),
                        'sell_price' => $cost['sell_price'],
                        'cost_per_unit' => $cost['actual_cost_per_jar'],
                        'profit_per_unit' => $cost['profit_per_unit'],
                        'food_cost_percent' => $cost['food_cost_percent'],
                    ];
                })->values(),
                'max_producible_units' => $calculateMaxProducibleUnits->handle($recipe),
                'ingredients' => BuildRecipeSheet::inLineOrder($recipe->mainIngredients, BuildRecipeSheet::pivotLine(...))->map(fn (Ingredient $ingredient) => array_merge(
                    [
                        'id' => $ingredient->id,
                        'name' => $ingredient->name,
                        'unit_type' => $ingredient->unit_type,
                        'is_house_made' => $ingredient->is_house_made,
                        'quantity_per_jar' => (float) $ingredient->pivot->quantity_per_jar,
                    ],
                    $calculateIngredientCosting->handle($ingredient)
                ))->values(),
                // Per-unit production sheet: prep, mix, pack -- the batch
                // calculator scales it to whatever size it's showing.
                'sheet' => $buildRecipeSheet->handle($recipe),
                'byproducts' => $recipe->byproductIngredients->sortBy('name')->map(fn (Ingredient $ingredient) => [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'byproduct_name' => $ingredient->byproduct_name,
                    'unit_type' => $ingredient->unit_type,
                    'quantity_per_jar' => (float) $ingredient->pivot->quantity_per_jar,
                ])->values(),
            ],
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Recipes', 'href' => route('admin.costing.recipes.index')],
                ['label' => $recipe->name],
            ),
        ]);
    }

    public function edit(Recipe $recipe, CalculateIngredientCosting $calculateIngredientCosting): Response
    {
        $this->authorize('update', $recipe);

        $recipe->load('mainIngredients', 'byproductIngredients', 'sizes.packaging');

        // 'priceHistory.ingredient' required: PriceHistoryEntry::price_per_unit
        // (and price_per_100g) reads $this->ingredient, and without this
        // eager load that lazy-loads per entry -- throws under this app's
        // Model::preventLazyLoading() outside production. Full model (not a
        // restricted column select) since CalculateIngredientCosting reads
        // several fields off each ingredient, including inventory.unit_size
        // and packageSizes.
        $ingredients = Ingredient::with('priceHistory.ingredient', 'inventory', 'packageSizes')->orderBy('name')->get();

        return Inertia::render('Vendor/costing/Recipes/Edit', [
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'notes' => $recipe->notes,
                'min_stock_threshold' => $recipe->min_stock_threshold,
                'sizes' => $recipe->sizes->map(fn (RecipeSize $size) => [
                    'id' => $size->id,
                    'label' => $size->label,
                    'fill_size_g' => (float) $size->fill_size_g,
                    'finished_good_id' => $size->finished_good_id,
                    'finished_good' => $this->finishedGoodOption($size->finished_good_id),
                    'packaging' => $size->packaging->map(fn (Ingredient $ingredient) => [
                        'ingredient_id' => $ingredient->id,
                        'quantity_per_unit' => (float) $ingredient->pivot->quantity_per_unit,
                    ])->values(),
                ])->values(),
                'preferred_batch_size' => $recipe->preferred_batch_size,
                'is_active' => $recipe->is_active,
                'ingredients' => BuildRecipeSheet::inLineOrder($recipe->mainIngredients, BuildRecipeSheet::pivotLine(...))->map(fn (Ingredient $ingredient) => [
                    'ingredient_id' => $ingredient->id,
                    'quantity_per_jar' => (float) $ingredient->pivot->quantity_per_jar,
                ]),
                'byproducts' => $recipe->byproductIngredients->map(fn (Ingredient $ingredient) => [
                    'ingredient_id' => $ingredient->id,
                    'quantity_per_jar' => (float) $ingredient->pivot->quantity_per_jar,
                ]),
            ],
            'ingredients' => $ingredients->map(fn (Ingredient $ingredient) => array_merge(
                [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'unit_type' => $ingredient->unit_type,
                    'byproduct_name' => $ingredient->byproduct_name,
                ],
                $calculateIngredientCosting->handle($ingredient)
            )),
            'packagingIngredients' => $this->packagingIngredients(),
            'existingRecipeNames' => Recipe::where('id', '!=', $recipe->id)->orderBy('name')->pluck('name'),
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Recipes', 'href' => route('admin.costing.recipes.index')],
                ['label' => $recipe->name],
            ),
        ]);
    }

    public function update(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $validated = $this->validated($request, $recipe->id);

        $recipe->fill([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
            'min_stock_threshold' => $validated['min_stock_threshold'] ?? null,
            'preferred_batch_size' => array_key_exists('preferred_batch_size', $validated) ? $validated['preferred_batch_size'] : $recipe->preferred_batch_size,
            'is_active' => $validated['is_active'] ?? true,
        ]);
        $savedEvent = CostingRecordSaved::forUpdated($recipe, auth()->id());
        $recipe->save();

        $recipe->mainIngredients()->sync($this->syncData($validated['ingredients']));
        $recipe->byproductIngredients()->sync($this->syncData($validated['byproducts'] ?? []));
        $this->syncSizes($recipe, $validated['sizes'] ?? []);

        event($savedEvent);

        // Background autosave from the Edit page: stay on it, no success
        // flash (SaveIndicator is the feedback).
        if ($request->boolean('stay')) {
            return redirect()->route('admin.costing.recipes.edit', $recipe);
        }

        return redirect()
            ->route('admin.costing.recipes.index')
            ->with('success', "Recipe '{$recipe->name}' updated.");
    }

    /**
     * Narrow, single-purpose update for just the costing field (the cost buffer -- the
     * price comes from the linked product and the fill weight from each size) -- mirrors
     * PriceHistoryController::updatePrice() / IngredientController::setPreferred(),
     * deliberately separate from the full update() action (which requires
     * the whole ingredients payload).
     */
    public function updateCosting(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $validated = $request->validate([
            'cost_buffer_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $recipe->fill($validated);
        $savedEvent = CostingRecordSaved::forUpdated($recipe, auth()->id());
        $recipe->save();
        event($savedEvent);

        // Not a hardcoded route -- redirects back to the Costing dashboard
        // that submitted this, matching the update-price/set-preferred fix.
        return redirect()->back()->with('success', "Costing for '{$recipe->name}' updated.");
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $this->authorize('delete', $recipe);

        $name = $recipe->name;
        $deletedEvent = CostingRecordDeleted::forModel($recipe, auth()->id());
        $recipe->delete();
        event($deletedEvent);

        return redirect()
            ->route('admin.costing.recipes.index')
            ->with('success', "Recipe '{$name}' deleted.");
    }

    /**
     * Multi-select actions from the Recipes table -- delete, plus
     * activate/deactivate since is_active is already a real, per-recipe
     * toggle (see Create/Edit.vue and scopeActive()), not something
     * invented just for this. Same per-item try/catch + tally pattern as
     * IngredientController::bulkAction().
     */
    /**
     * Hard-deletes a recipe the Create form autosaved into existence and
     * the admin then discarded (Edit page × -> "Discard Recipe").
     * RecipePolicy::discardDraft limits it to fresh recipes no production
     * run uses yet.
     */
    public function discardDraft(Recipe $recipe): RedirectResponse
    {
        $this->authorize('discardDraft', $recipe);

        $deletedEvent = CostingRecordDeleted::forModel($recipe, auth()->id(), ['discarded_draft' => true]);
        $recipe->delete();
        event($deletedEvent);

        return redirect()
            ->route('admin.costing.recipes.index')
            ->with('success', 'Recipe discarded.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['delete', 'activate', 'deactivate'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:costing_recipes,id'],
        ]);

        $successCount = 0;
        $failCount = 0;

        foreach ($validated['ids'] as $id) {
            try {
                $recipe = Recipe::findOrFail($id);
                $context = ['source' => 'bulk_action', 'bulk_action' => $validated['action']];

                if ($validated['action'] === 'delete') {
                    $this->authorize('delete', $recipe);
                    $deletedEvent = CostingRecordDeleted::forModel($recipe, auth()->id(), $context);
                    $recipe->delete();
                    event($deletedEvent);
                } else {
                    $this->authorize('update', $recipe);
                    // Same rule as the form: an active recipe needs a finished product.
                    if ($validated['action'] === 'activate' && !$this->hasLinkedProduct($recipe)) {
                        throw new \RuntimeException('Active recipes need a finished product.');
                    }
                    $recipe->fill(['is_active' => $validated['action'] === 'activate']);
                    $savedEvent = CostingRecordSaved::forUpdated($recipe, auth()->id(), $context);
                    $recipe->save();
                    event($savedEvent);
                }

                $successCount++;
            } catch (\Throwable) {
                $failCount++;
            }
        }

        $verb = match ($validated['action']) {
            'delete' => 'Deleted',
            'activate' => 'Activated',
            'deactivate' => 'Deactivated',
        };
        $message = "{$verb} {$successCount} recipe".($successCount === 1 ? '' : 's').'.';
        if ($failCount > 0) {
            $message .= " {$failCount} could not be updated.";
        }

        return redirect()->back()->with($failCount === 0 ? 'success' : 'warning', $message);
    }

    /**
     * A linked finished good's id/label only (never a raw Eloquent model
     * passed to Vue -- see .claude/CLAUDE.md), for the picker's initial
     * display. null when unlinked, or when the linked id no longer resolves
     * (e.g. deleted on the host side).
     *
     * @return array{id: int, label: string, sublabel: string|null}|null
     */
    private function finishedGoodOption(?int $productId): ?array
    {
        if ($productId === null) {
            return null;
        }

        $finishedGood = app(FinishedGoodRepository::class)->find($productId);

        return $finishedGood ? [
            'id' => $finishedGood->getId(),
            'label' => $finishedGood->getLabel(),
            'sublabel' => $finishedGood->getSublabel(),
        ] : null;
    }

    /** Active recipes need at least one size with a product to credit. */
    private function hasLinkedProduct(Recipe $recipe): bool
    {
        return $recipe->sizes()->whereNotNull('finished_good_id')->exists();
    }

    /** Ingredients that can be attached to a size as packaging. */
    private function packagingIngredients()
    {
        return Ingredient::orderBy('name')->get(['id', 'name', 'unit_type', 'category'])
            ->filter(fn (Ingredient $ingredient) => $ingredient->isPackaging())
            ->map(fn (Ingredient $ingredient) => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'unit_type' => $ingredient->unit_type,
            ])->values();
    }

    /**
     * Makes the recipe's sizes match the submitted list: rows with an id
     * are updated, rows without are created, and sizes left out are removed
     * -- unless a production run has used them, which would erase that
     * run's record, so that is refused instead.
     *
     * @param  array<int, array>  $sizes
     */
    private function syncSizes(Recipe $recipe, array $sizes): void
    {
        $existing = $recipe->sizes()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($sizes) as $index => $row) {
            $size = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            $fill = (float) $row['fill_size_g'];

            $attributes = [
                'label' => ($row['label'] ?? '') !== '' ? $row['label'] : rtrim(rtrim(number_format($fill, 2, '.', ''), '0'), '.').'g',
                'fill_size_g' => $fill,
                'finished_good_id' => $row['finished_good_id'] ?? null,
                'sort_order' => $index,
            ];

            $size = $size ?? new RecipeSize(['recipe_id' => $recipe->id]);
            $size->fill($attributes)->save();
            $keep[] = $size->id;

            $size->packaging()->sync(collect($row['packaging'] ?? [])
                ->mapWithKeys(fn (array $line) => [(int) $line['ingredient_id'] => ['quantity_per_unit' => $line['quantity_per_unit']]])
                ->all());
        }

        foreach ($existing->except($keep) as $removed) {
            if ($removed->allocations()->exists()) {
                throw ValidationException::withMessages([
                    'sizes' => "The {$removed->label} size has been used in a production run and can't be removed.",
                ]);
            }
            $removed->delete();
        }
    }

    /**
     * Live search backing the "Finished product" picker -- returns id/label
     * pairs only, resolved through the host app's own FinishedGoodRepository
     * binding rather than this package querying a host table directly.
     */
    public function searchFinishedGoods(Request $request, FinishedGoodRepository $finishedGoods): JsonResponse
    {
        $this->authorize('viewAny', Recipe::class);

        $results = $finishedGoods->search((string) $request->query('q', ''), 20);

        return response()->json([
            'results' => array_map(fn ($finishedGood) => [
                'id' => $finishedGood->getId(),
                'label' => $finishedGood->getLabel(),
                'sublabel' => $finishedGood->getSublabel(),
            ], $results),
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('costing_recipes', 'name')->ignore($ignoreId),
            ],
            'notes' => ['nullable', 'string'],
            'sizes' => [
                'array',
                // Active recipes must be able to credit a product when a run
                // completes: at least one size, and a product on every size.
                Rule::requiredIf(fn () => $request->boolean('is_active', true)),
                function ($attribute, $value, $fail) use ($request) {
                    if (!$request->boolean('is_active', true)) {
                        return;
                    }
                    if (collect($value)->isEmpty()) {
                        $fail('An active recipe needs at least one size.');
                    } elseif (collect($value)->contains(fn ($row) => empty($row['finished_good_id']))) {
                        $fail('Every size of an active recipe needs a finished product.');
                    }
                },
            ],
            'sizes.*.id' => ['nullable', 'integer'],
            'sizes.*.label' => ['nullable', 'string', 'max:255'],
            'sizes.*.fill_size_g' => ['required', 'numeric', 'gt:0', 'distinct'],
            'sizes.*.finished_good_id' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if ($value !== null && app(FinishedGoodRepository::class)->find((int) $value) === null) {
                    $fail('The selected finished product is invalid.');
                }
            }],
            'sizes.*.packaging' => ['nullable', 'array'],
            'sizes.*.packaging.*.ingredient_id' => ['required', 'exists:costing_ingredients,id'],
            'sizes.*.packaging.*.quantity_per_unit' => ['required', 'numeric', 'min:0'],
            'min_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'preferred_batch_size' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'ingredients' => ['array'],
            'ingredients.*.ingredient_id' => ['required', 'exists:costing_ingredients,id'],
            'ingredients.*.quantity_per_jar' => ['required', 'numeric', 'min:0'],
            'byproducts' => ['nullable', 'array'],
            'byproducts.*.ingredient_id' => ['required', 'exists:costing_ingredients,id'],
            'byproducts.*.quantity_per_jar' => ['required', 'numeric', 'min:0'],
        ], [
            'sizes.required' => 'An active recipe needs at least one size.',
        ]);
    }

    /**
     * @param array<int, array{ingredient_id: int, quantity_per_jar: float}> $ingredients
     * @return array<int, array{quantity_per_jar: float}>
     */
    private function syncData(array $ingredients): array
    {
        $syncData = [];
        foreach ($ingredients as $row) {
            // Zero means "not used", same as the original sheet -- skip it
            // rather than storing a meaningless pivot row.
            if ((float) $row['quantity_per_jar'] <= 0) {
                continue;
            }
            $syncData[$row['ingredient_id']] = ['quantity_per_jar' => $row['quantity_per_jar']];
        }

        return $syncData;
    }
}
