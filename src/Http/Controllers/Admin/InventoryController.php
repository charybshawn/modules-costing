<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\CheckIngredientLowStock;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\InventoryItem;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller implements HasMiddleware
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
        $this->authorize('viewAny', InventoryItem::class);

        $ingredients = Ingredient::with('inventory')
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $ingredient) => [
                'ingredient_id' => $ingredient->id,
                'name' => $ingredient->name,
                'category' => $ingredient->category,
                'unit_type' => $ingredient->unit_type,
                'unit_size' => (float) ($ingredient->inventory->unit_size ?? 0),
                'units_on_hand' => (float) ($ingredient->inventory->units_on_hand ?? 0),
                'on_hand' => (float) ($ingredient->inventory->on_hand ?? 0),
                'notes' => $ingredient->inventory->notes ?? null,
            ]);

        return Inertia::render('Vendor/costing/Inventory/Index', [
            'ingredients' => $ingredients,
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Inventory']),
        ]);
    }

    public function edit(Ingredient $ingredient): Response
    {
        $ingredient->loadMissing('inventory', 'packageSizes');
        $this->authorize('update', $ingredient->inventory);

        return Inertia::render('Vendor/costing/Inventory/Edit', [
            'ingredient' => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'unit_type' => $ingredient->unit_type,
            ],
            'inventory' => [
                'counted_on_hand' => $ingredient->inventory->counted_on_hand !== null ? (float) $ingredient->inventory->counted_on_hand : null,
                'notes' => $ingredient->inventory->notes,
            ],
            // Drives the "Stock on Hand, by Brand" walk-in count table --
            // one row per known package size (brand-specific first, plus a
            // generic "Other" row from weight_per_unit if set) -- so stock
            // is entered per real box/case size (2 boxes of this, 4 of
            // that) without the user converting to one unit size in their
            // head first.
            'package_sizes' => $ingredient->packageSizes->map(fn ($ps) => [
                'provider' => $ps->provider,
                'brand' => $ps->brand,
                'package_size' => (float) $ps->package_size,
            ])->values(),
            'weight_per_unit' => $ingredient->weight_per_unit !== null ? (float) $ingredient->weight_per_unit : null,
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Inventory', 'href' => route('admin.costing.inventory.index')],
                ['label' => $ingredient->name],
            ),
        ]);
    }

    public function update(Request $request, Ingredient $ingredient, CheckIngredientLowStock $checkIngredientLowStock): RedirectResponse
    {
        $ingredient->loadMissing('inventory');
        $this->authorize('update', $ingredient->inventory);

        $validated = $request->validate([
            // The "Stock on Hand, by Brand" walk-in count table
            // (Inventory/Edit.vue) is the only way this form enters stock
            // now -- the raw counted total. Nullable because leaving every
            // row in the table untouched (e.g. an edit that only changes
            // Notes) submits null, which intentionally leaves on_hand
            // computing off the old unit_size x units_on_hand pair
            // untouched rather than zeroing it out. unit_size/units_on_hand
            // themselves are legacy columns this form no longer writes to
            // at all -- CalculateIngredientCosting's purchase_size fallback
            // reads Ingredient.weight_per_unit instead, and on_hand's
            // fallback formula only ever reads the columns, never needs
            // them submitted here again.
            'counted_on_hand' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $oldOnHand = (float) $ingredient->inventory->on_hand;
        $ingredient->inventory->update($validated);

        // Manual stock corrections can cross the threshold too, not just
        // the auto-deduct-on-complete path -- "notify whenever an
        // inventory item is low" isn't scoped to one trigger.
        $checkIngredientLowStock->handle($ingredient, $oldOnHand, (float) $ingredient->inventory->fresh()->on_hand);

        return redirect()
            ->route('admin.costing.inventory.index')
            ->with('success', "Inventory for '{$ingredient->name}' updated.");
    }

    /**
     * Update stock for several ingredients in one submission, either
     * "received" (adds the entered quantity on top of what's on hand --
     * a restock-after-shopping entry) or "recount" (the entered quantity
     * replaces the total outright, same semantics as update() above, just
     * for many ingredients at once). Operates on counted_on_hand directly
     * for any ingredient already using it, else falls back to the legacy
     * units_on_hand column -- same branching CompleteProductionRun uses.
     */
    public function bulkUpdate(Request $request, CheckIngredientLowStock $checkIngredientLowStock): RedirectResponse
    {
        $this->authorize('bulkUpdate', InventoryItem::class);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['received', 'recount'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'distinct', 'exists:costing_ingredients,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $checkIngredientLowStock) {
            foreach ($validated['items'] as $item) {
                $ingredient = Ingredient::with('inventory')->find($item['ingredient_id']);
                $inventory = $ingredient->inventory;

                $oldOnHand = (float) $inventory->on_hand;

                if ($inventory->counted_on_hand !== null) {
                    // Walk-in-counted ingredient -- operate on the raw
                    // counted total directly, same reasoning as
                    // CompleteProductionRun: unit_size/units_on_hand aren't
                    // authoritative once a counted total exists.
                    $newCountedOnHand = $validated['mode'] === 'received'
                        ? (float) $inventory->counted_on_hand + (float) $item['quantity']
                        : (float) $item['quantity'];

                    $inventory->update(['counted_on_hand' => $newCountedOnHand]);
                } else {
                    $newUnitsOnHand = $validated['mode'] === 'received'
                        ? (float) $inventory->units_on_hand + (float) $item['quantity']
                        : (float) $item['quantity'];

                    $inventory->update(['units_on_hand' => $newUnitsOnHand]);
                }

                $checkIngredientLowStock->handle($ingredient, $oldOnHand, (float) $inventory->fresh()->on_hand);
            }
        });

        $count = count($validated['items']);

        return redirect()
            ->route('admin.costing.inventory.index')
            ->with('success', $validated['mode'] === 'received'
                ? "Stock received for {$count} ingredient(s)."
                : "Inventory recounted for {$count} ingredient(s).");
    }
}
