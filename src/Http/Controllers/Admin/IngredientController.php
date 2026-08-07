<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\CalculateIngredientCosting;
use Cultpantry\Costing\Actions\GetIngredientPriceOptions;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\JsonResponse;
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
                abort_unless($request->user()?->isAdmin(), 403, 'Admin access required.');
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
        // purchase_size/purchase_unit.
        $ingredients = Ingredient::with('priceHistory.ingredient', 'inventory', 'packageSizes')
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
                    'low_stock_threshold' => $ingredient->low_stock_threshold !== null ? (float) $ingredient->low_stock_threshold : null,
                    'byproduct_name' => $ingredient->byproduct_name,
                    'source_count' => $ingredient->packageSizes->count(),
                ],
                $calculateIngredientCosting->handle($ingredient)
            ));

        return Inertia::render('Vendor/costing/Ingredients/Index', [
            'ingredients' => $ingredients,
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Ingredients']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Ingredient::class);

        return Inertia::render('Vendor/costing/Ingredients/Create', [
            'categories' => $this->knownCategories(),
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

        $ingredient = Ingredient::create($validated);

        return redirect()
            ->route('admin.costing.ingredients.index')
            ->with('success', "Ingredient '{$ingredient->name}' created.");
    }

    public function edit(Ingredient $ingredient): Response
    {
        $this->authorize('update', $ingredient);

        return Inertia::render('Vendor/costing/Ingredients/Edit', [
            'ingredient' => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'category' => $ingredient->category,
                'unit_type' => $ingredient->unit_type,
                'waste_percent' => (float) $ingredient->waste_percent,
                'notes' => $ingredient->notes,
                'low_stock_threshold' => $ingredient->low_stock_threshold !== null ? (float) $ingredient->low_stock_threshold : null,
                'byproduct_name' => $ingredient->byproduct_name,
            ],
            'categories' => $this->knownCategories(),
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

        $ingredient->update($validated);

        return redirect()
            ->route('admin.costing.ingredients.index')
            ->with('success', "Ingredient '{$ingredient->name}' updated.");
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

        $ingredient->update([
            'preferred_source' => $validated['provider'] ?? null,
            'preferred_brand' => $validated['brand'] ?? null,
        ]);

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
        ]);

        PackageSize::updateOrCreate(
            [
                'ingredient_id' => $ingredient->id,
                'provider' => $validated['provider'],
                'brand' => $validated['brand'] ?? null,
            ],
            ['package_size' => $validated['package_size']],
        );

        // Not a hardcoded route -- same reasoning as setPreferred() above,
        // this is called from the same reused Available Prices modal.
        return redirect()->back()->with('success', "Package size for '{$ingredient->name}' ({$validated['provider']}) updated.");
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('delete', $ingredient);

        $name = $ingredient->name;
        $ingredient->delete();

        return redirect()
            ->route('admin.costing.ingredients.index')
            ->with('success', "Ingredient '{$name}' deleted.");
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('costing_ingredients', 'name')->ignore($ignoreId),
            ],
            'category' => ['nullable', 'string', 'max:255'],
            'unit_type' => ['required', Rule::in(['g', 'unit'])],
            'waste_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'byproduct_name' => ['nullable', 'string', 'max:100'],
        ]);
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
