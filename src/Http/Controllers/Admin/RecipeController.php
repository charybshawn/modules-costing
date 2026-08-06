<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\CalculateIngredientCosting;
use Cultpantry\Costing\Actions\CalculateRecipeCost;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Models\RecipeCostSnapshot;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless($request->user()?->isAdmin(), 403, 'Admin access required.');
                return $next($request);
            }),
            new Middleware(function ($request, $next) {
                abort_unless(app(GetSiteSetting::class)->handle('modules.cultpantry/costing.enabled', true), 404);
                return $next($request);
            }),
        ];
    }

    public function index(): Response
    {
        $this->authorize('viewAny', Recipe::class);

        $recipes = Recipe::withCount('ingredients')
            ->orderBy('name')
            ->get(['id', 'name', 'notes']);

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
        $recipes = Recipe::with('ingredients')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Vendor/costing/Recipes/Grid', [
            'ingredients' => $ingredients,
            'recipes' => $recipes->map(fn (Recipe $recipe) => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'quantities' => $recipe->ingredients->mapWithKeys(
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
        $recipes = Recipe::with('mainIngredients.priceHistory.ingredient', 'mainIngredients.inventory', 'mainIngredients.packageSizes')->orderBy('name')->get();

        return Inertia::render('Vendor/costing/Recipes/Costing', [
            'recipes' => $recipes->map(fn (Recipe $recipe) => array_merge(
                [
                    'id' => $recipe->id,
                    'name' => $recipe->name,
                    'sell_price' => $recipe->sell_price !== null ? (float) $recipe->sell_price : null,
                    'fill_size_g' => $recipe->fill_size_g !== null ? (float) $recipe->fill_size_g : null,
                    'cost_buffer_percent' => $recipe->cost_buffer_percent !== null ? (float) $recipe->cost_buffer_percent : null,
                ],
                $calculateRecipeCost->handle($recipe)
            )),
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

        $snapshots = RecipeCostSnapshot::with('recipe:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (RecipeCostSnapshot $snapshot) => [
                'id' => $snapshot->id,
                'recipe_id' => $snapshot->recipe_id,
                'recipe_name' => $snapshot->recipe?->name,
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
        ]);

        $recipe->mainIngredients()->sync($this->syncData($validated['ingredients']));
        $recipe->byproductIngredients()->sync($this->syncData($validated['byproducts'] ?? []));

        return redirect()
            ->route('admin.costing.recipes.index')
            ->with('success', "Recipe '{$recipe->name}' created.");
    }

    public function edit(Recipe $recipe, CalculateIngredientCosting $calculateIngredientCosting): Response
    {
        $this->authorize('update', $recipe);

        $recipe->load('mainIngredients', 'byproductIngredients');

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
                'ingredients' => $recipe->mainIngredients->map(fn (Ingredient $ingredient) => [
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

        $recipe->update([
            'name' => $validated['name'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $recipe->mainIngredients()->sync($this->syncData($validated['ingredients']));
        $recipe->byproductIngredients()->sync($this->syncData($validated['byproducts'] ?? []));

        return redirect()
            ->route('admin.costing.recipes.index')
            ->with('success', "Recipe '{$recipe->name}' updated.");
    }

    /**
     * Narrow, single-purpose update for just the costing fields -- mirrors
     * PriceHistoryController::updatePrice() / IngredientController::setPreferred(),
     * deliberately separate from the full update() action (which requires
     * the whole ingredients payload).
     */
    public function updateCosting(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $validated = $request->validate([
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'fill_size_g' => ['nullable', 'numeric', 'min:0'],
            'cost_buffer_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $recipe->update($validated);

        // Not a hardcoded route -- redirects back to the Costing dashboard
        // that submitted this, matching the update-price/set-preferred fix.
        return redirect()->back()->with('success', "Costing for '{$recipe->name}' updated.");
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $this->authorize('delete', $recipe);

        $name = $recipe->name;
        $recipe->delete();

        return redirect()
            ->route('admin.costing.recipes.index')
            ->with('success', "Recipe '{$name}' deleted.");
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('costing_recipes', 'name')->ignore($ignoreId),
            ],
            'notes' => ['nullable', 'string'],
            'ingredients' => ['array'],
            'ingredients.*.ingredient_id' => ['required', 'exists:costing_ingredients,id'],
            'ingredients.*.quantity_per_jar' => ['required', 'numeric', 'min:0'],
            'byproducts' => ['nullable', 'array'],
            'byproducts.*.ingredient_id' => ['required', 'exists:costing_ingredients,id'],
            'byproducts.*.quantity_per_jar' => ['required', 'numeric', 'min:0'],
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
