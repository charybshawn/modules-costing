<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\CheckIngredientLowStock;
use Cultpantry\Costing\Actions\RecordInventoryAdjustment;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\InventoryAdjustment;
use Cultpantry\Costing\Models\InventoryItem;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\JsonResponse;
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

        $ingredients = Ingredient::with('inventory', 'packageSizes')
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $ingredient) => [
                'ingredient_id' => $ingredient->id,
                'name' => $ingredient->name,
                'category' => $ingredient->category,
                'unit_type' => $ingredient->unit_type,
                'on_hand' => (float) $ingredient->packageSizes->sum('quantity_on_hand'),
                'source_count' => $ingredient->packageSizes->count(),
                'notes' => $ingredient->inventory->notes ?? null,
            ]);

        return Inertia::render('Vendor/costing/Inventory/Index', [
            'ingredients' => $ingredients,
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Inventory']),
        ]);
    }

    /**
     * This ingredient's sources (provider/brand rows) with current on-hand
     * -- fetched on demand by StockAdjustModal.vue on open, same pattern as
     * IngredientController::priceOptions() backing AvailablePricesModal.
     */
    public function sources(Ingredient $ingredient): JsonResponse
    {
        $this->authorize('view', $ingredient->inventory);

        $ingredient->loadMissing('packageSizes');

        return response()->json([
            'sources' => $ingredient->packageSizes->map(fn (PackageSize $packageSize) => [
                'id' => $packageSize->id,
                'provider' => $packageSize->provider,
                'brand' => $packageSize->brand,
                'package_size' => (float) $packageSize->package_size,
                'quantity_on_hand' => (float) $packageSize->quantity_on_hand,
                'packages' => (float) $packageSize->packages,
            ])->values(),
        ]);
    }

    /**
     * Two explicit, mutually exclusive workflows on one source: 'recount'
     * (a physical count of this source, replaces its quantity outright) or
     * 'adjust' (a package count added to or subtracted from what's there --
     * stock received or a correction/writeoff). Reason is derived from
     * direction rather than asked for separately (add is always received,
     * subtract is always correction) -- one fewer decision for someone
     * standing in a walk-in cooler on their phone.
     */
    public function adjustSource(
        Request $request,
        Ingredient $ingredient,
        PackageSize $packageSize,
        CheckIngredientLowStock $checkIngredientLowStock,
        RecordInventoryAdjustment $recordInventoryAdjustment,
    ): RedirectResponse {
        $ingredient->loadMissing('inventory');
        $this->authorize('update', $ingredient->inventory);
        abort_unless($packageSize->ingredient_id === $ingredient->id, 404);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['recount', 'adjust'])],
            'packages' => ['required', 'numeric', 'min:0'],
            'direction' => ['required_if:mode,adjust', 'nullable', Rule::in(['add', 'subtract'])],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $ingredientOldOnHand = (float) $ingredient->inventory->on_hand;
        $sourceOldOnHand = (float) $packageSize->quantity_on_hand;
        $quantity = (float) $validated['packages'] * (float) $packageSize->package_size;

        if ($validated['mode'] === 'recount') {
            $sourceNewOnHand = $quantity;
            $reason = 'recount';
        } else {
            $delta = $quantity * ($validated['direction'] === 'add' ? 1 : -1);
            $sourceNewOnHand = max(0, $sourceOldOnHand + $delta);
            $reason = $validated['direction'] === 'add' ? 'received' : 'correction';
        }

        $packageSize->update(['quantity_on_hand' => $sourceNewOnHand]);

        $recordInventoryAdjustment->handle(
            packageSize: $packageSize,
            reason: $reason,
            onHandBefore: $sourceOldOnHand,
            onHandAfter: $sourceNewOnHand,
            notes: $validated['notes'] ?? null,
            userId: $request->user()?->id,
        );

        $ingredientNewOnHand = $ingredientOldOnHand + ($sourceNewOnHand - $sourceOldOnHand);

        // Manual stock corrections can cross the threshold too, not just
        // the auto-deduct-on-complete path -- "notify whenever an
        // inventory item is low" isn't scoped to one trigger.
        $checkIngredientLowStock->handle($ingredient, $ingredientOldOnHand, $ingredientNewOnHand);

        return redirect()
            ->route('admin.costing.inventory.index')
            ->with('success', "Inventory for '{$ingredient->name}' updated.");
    }

    /**
     * Removes a source entirely -- e.g. no longer buying from that
     * supplier. Blocked while it still has stock (recount it to 0 first;
     * silently discarding real inventory as a side effect of "delete" would
     * be exactly the kind of unlogged change this whole feature exists to
     * prevent). Its adjustment history survives the delete (package_size_id
     * -> null, source_provider/source_brand already snapshotted).
     */
    public function destroySource(Ingredient $ingredient, PackageSize $packageSize): RedirectResponse
    {
        $ingredient->loadMissing('inventory');
        $this->authorize('update', $ingredient->inventory);
        abort_unless($packageSize->ingredient_id === $ingredient->id, 404);

        abort_if(
            (float) $packageSize->quantity_on_hand > 0,
            422,
            'This source still has stock on hand -- recount it to 0 first, then remove it.',
        );

        $name = $packageSize->brand ? "{$packageSize->provider} — {$packageSize->brand}" : $packageSize->provider;
        $packageSize->delete();

        return redirect()
            ->route('admin.costing.inventory.index')
            ->with('success', "Removed '{$name}' as a source for '{$ingredient->name}'.");
    }

    /**
     * Update stock for several ingredients in one submission, either
     * "adjust" (a quantity added to or subtracted from what's on hand) or
     * "recount" (the entered quantity replaces the total outright).
     * Targets each ingredient's preferred source, falling back to its first
     * real source if no preference is set, rather than adding a per-row
     * source picker to the bulk modal -- a bulk update is normally one
     * shopping trip restocking the usual supplier across many items at
     * once, not a mixed-source correction pass (that's what the
     * per-ingredient modal is for). An ingredient with no sources at all
     * has nothing to attach a quantity to, so it's skipped rather than
     * updated -- add a real source for it first.
     */
    public function bulkUpdate(
        Request $request,
        CheckIngredientLowStock $checkIngredientLowStock,
        RecordInventoryAdjustment $recordInventoryAdjustment,
    ): RedirectResponse {
        $this->authorize('bulkUpdate', InventoryItem::class);

        // A recount can't be negative (it's a physical count), but an
        // adjust batch needs to allow it (a correction/writeoff) -- so the
        // quantity rule itself depends on mode, decided before validate()
        // runs rather than trying to express it as one static rule.
        $quantityRule = $request->input('mode') === 'recount'
            ? ['required', 'numeric', 'min:0']
            : ['required', 'numeric'];

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['recount', 'adjust'])],
            // One reason/notes pair for the whole batch, not per row -- a
            // bulk update is normally one shopping trip or one correction
            // pass, so that's the right grain, not forcing the same detail
            // to be typed once per ingredient.
            'reason' => ['required_if:mode,adjust', 'nullable', Rule::in(['received', 'correction'])],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'distinct', 'exists:costing_ingredients,id'],
            'items.*.quantity' => $quantityRule,
        ]);

        $reason = $validated['mode'] === 'recount' ? 'recount' : $validated['reason'];

        $skipped = [];

        DB::transaction(function () use ($validated, $reason, $checkIngredientLowStock, $recordInventoryAdjustment, $request, &$skipped) {
            foreach ($validated['items'] as $item) {
                $ingredient = Ingredient::with('inventory', 'packageSizes')->find($item['ingredient_id']);

                $packageSize = $ingredient->packageSizes
                    ->first(fn (PackageSize $packageSize) => $ingredient->preferred_source
                        && $packageSize->provider === $ingredient->preferred_source
                        && (!$ingredient->preferred_brand || $packageSize->brand === $ingredient->preferred_brand))
                    ?? $ingredient->packageSizes->first();

                if ($packageSize === null) {
                    $skipped[] = $ingredient->name;
                    continue;
                }

                $ingredientOldOnHand = (float) $ingredient->inventory->on_hand;
                $sourceOldOnHand = (float) $packageSize->quantity_on_hand;

                $sourceNewOnHand = $validated['mode'] === 'adjust'
                    ? max(0, $sourceOldOnHand + (float) $item['quantity'])
                    : (float) $item['quantity'];

                $packageSize->update(['quantity_on_hand' => $sourceNewOnHand]);

                $recordInventoryAdjustment->handle(
                    packageSize: $packageSize,
                    reason: $reason,
                    onHandBefore: $sourceOldOnHand,
                    onHandAfter: $sourceNewOnHand,
                    notes: $validated['notes'] ?? null,
                    userId: $request->user()?->id,
                );

                $ingredientNewOnHand = $ingredientOldOnHand + ($sourceNewOnHand - $sourceOldOnHand);

                $checkIngredientLowStock->handle($ingredient, $ingredientOldOnHand, $ingredientNewOnHand);
            }
        });

        $count = count($validated['items']) - count($skipped);

        $message = $validated['mode'] === 'adjust'
            ? "Stock adjusted for {$count} ingredient(s)."
            : "Inventory recounted for {$count} ingredient(s).";

        if ($skipped !== []) {
            $message .= ' Skipped (no source on file yet): '.implode(', ', $skipped).'.';
        }

        return redirect()
            ->route('admin.costing.inventory.index')
            ->with('success', $message);
    }

    /**
     * Every InventoryAdjustment row, across all ingredients -- the audit
     * trail for recounts, manual adjusts, and automatic production-run
     * deductions alike. Reuses InventoryItem's 'viewAny' ability rather than
     * a dedicated policy -- this is a read-only view into the same
     * inventory data, not a separate permission domain.
     */
    public function adjustments(): Response
    {
        $this->authorize('viewAny', InventoryItem::class);

        $adjustments = InventoryAdjustment::with('ingredient', 'packageSize', 'user', 'productionRun')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InventoryAdjustment $adjustment) => [
                'id' => $adjustment->id,
                'ingredient_name' => $adjustment->ingredient->name,
                'unit_type' => $adjustment->ingredient->unit_type,
                // Reads the snapshot columns, not the live packageSize
                // relation -- a deleted source leaves package_size_id null,
                // and history has to stay readable regardless.
                'source' => $adjustment->source_brand
                    ? "{$adjustment->source_provider} — {$adjustment->source_brand}"
                    : $adjustment->source_provider,
                'reason' => $adjustment->reason,
                'delta' => (float) $adjustment->delta,
                'on_hand_before' => (float) $adjustment->on_hand_before,
                'on_hand_after' => (float) $adjustment->on_hand_after,
                'notes' => $adjustment->notes,
                'user_name' => $adjustment->user?->name,
                'production_run_name' => $adjustment->productionRun
                    ? ($adjustment->productionRun->name ?? $adjustment->productionRun->run_date->format('Y-m-d'))
                    : null,
                'production_run_id' => $adjustment->production_run_id,
                'created_at' => $adjustment->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Vendor/costing/Inventory/Adjustments', [
            'adjustments' => $adjustments,
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Inventory', 'href' => route('admin.costing.inventory.index')],
                ['label' => 'Adjustment History'],
            ),
        ]);
    }
}
