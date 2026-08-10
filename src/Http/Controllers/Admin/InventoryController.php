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

        $ingredients = Ingredient::with('packageSizes')
            ->orderBy('name')
            ->get()
            ->map(function (Ingredient $ingredient) {
                return [
                    'ingredient_id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'category' => $ingredient->category,
                    'unit_type' => $ingredient->unit_type,
                    'on_hand' => (float) $ingredient->packageSizes->sum('quantity_on_hand'),
                    'source_count' => $ingredient->packageSizes->count(),
                    // Every real source, so the bulk modal can let the user
                    // pick which one a stock update actually applies to --
                    // it's no longer forced onto the preferred/first source.
                    'sources' => $ingredient->packageSizes->map(fn (PackageSize $packageSize) => [
                        'id' => $packageSize->id,
                        'provider' => $packageSize->provider,
                        'brand' => $packageSize->brand,
                        'package_size' => (float) $packageSize->package_size,
                        'units_per_case' => (int) $packageSize->units_per_case,
                    ])->values(),
                    // Just a sensible default to preselect in the source
                    // dropdown -- not authoritative like it used to be when
                    // bulkUpdate() had no picker and had to resolve this itself.
                    'preferred_source_id' => $this->bulkUpdateTargetSource($ingredient)?->id,
                ];
            });

        return Inertia::render('Vendor/costing/Inventory/Index', [
            'ingredients' => $ingredients,
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Inventory']),
        ]);
    }

    /**
     * Onboards an item that doesn't exist in the system at all yet --
     * creates the Ingredient, its first Source (package size only, no
     * price -- logged separately via Price History whenever convenient,
     * same as a source added from the per-item Stock modal), and an
     * optional starting on-hand quantity, all in one submission. Previously
     * this needed three separate screens (Add Ingredient, find it again,
     * add a source, then a separate recount) before a brand-new item had
     * any usable stock. An ingredient that already exists but just needs a
     * new source still goes through the per-item Stock modal, not this.
     */
    public function storeItem(Request $request, RecordInventoryAdjustment $recordInventoryAdjustment): RedirectResponse
    {
        $this->authorize('create', Ingredient::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('costing_ingredients', 'name')],
            'category' => ['nullable', 'string', 'max:255'],
            'unit_type' => ['required', Rule::in(['g', 'unit'])],
            'waste_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'provider' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'package_size' => ['required', 'numeric', 'min:0.01'],
            // Nullable here, unlike setPackageSize()'s required version --
            // this is a one-shot create with no prior value to accidentally
            // clobber, so a plain 1-default is safe.
            'units_per_case' => ['nullable', 'integer', 'min:1'],
            // Packages, not a raw base-unit figure -- same reasoning as
            // bulkUpdate() above: the physical count is what's actually
            // known at intake time, not its gram equivalent.
            'packages' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $request, $recordInventoryAdjustment) {
            $ingredient = Ingredient::create([
                'name' => $validated['name'],
                'category' => $validated['category'] ?? null,
                'unit_type' => $validated['unit_type'],
                'waste_percent' => $validated['waste_percent'],
            ]);

            $packageSize = PackageSize::create([
                'ingredient_id' => $ingredient->id,
                'provider' => $validated['provider'],
                'brand' => $validated['brand'] ?? null,
                'package_size' => $validated['package_size'],
                'units_per_case' => $validated['units_per_case'] ?? 1,
                'quantity_on_hand' => 0,
            ]);

            $packages = (float) ($validated['packages'] ?? 0);
            if ($packages > 0) {
                $quantity = $packages * (float) $validated['package_size'];
                $packageSize->update(['quantity_on_hand' => $quantity]);

                $recordInventoryAdjustment->handle(
                    packageSize: $packageSize,
                    reason: 'received',
                    onHandBefore: 0,
                    onHandAfter: $quantity,
                    notes: 'Initial stock (new item)',
                    userId: $request->user()?->id,
                );
            }
        });

        return redirect()
            ->route('admin.costing.inventory.index')
            ->with('success', "'{$validated['name']}' added to Inventory.");
    }

    /**
     * This ingredient's sources (provider/brand rows) with current on-hand
     * -- fetched on demand by StockAdjustModal.vue on open, same pattern as
     * IngredientController::priceOptions() backing AvailablePricesModal.
     */
    public function sources(Ingredient $ingredient): JsonResponse
    {
        // loadMissing BEFORE authorize -- route-model binding gives a bare
        // Ingredient, and touching ->inventory unloaded throws under this
        // app's Model::preventLazyLoading() outside production.
        $ingredient->loadMissing('inventory', 'packageSizes');
        $this->authorize('view', $ingredient->inventory);

        return response()->json([
            'sources' => $ingredient->packageSizes->map(fn (PackageSize $packageSize) => [
                'id' => $packageSize->id,
                'provider' => $packageSize->provider,
                'brand' => $packageSize->brand,
                'package_size' => (float) $packageSize->package_size,
                'units_per_case' => (int) $packageSize->units_per_case,
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

        // back(), not a hardcoded index redirect -- StockAdjustModal calls
        // this with preserveState so the modal survives the round-trip and
        // several sources can be adjusted in one session (the previous
        // index redirect force-remounted the page and closed the modal
        // after every single mutation).
        return redirect()->back()->with('success', "Inventory for '{$ingredient->name}' updated.");
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

        // back(), not a hardcoded index redirect -- this is called from
        // StockAdjustModal (Inventory), but also from SourcesTable, which
        // is embedded on Ingredients/Edit and inside AvailablePricesModal
        // on both the Ingredients list and Recipes/Edit. The old hardcoded
        // redirect threw all three of those onto the Inventory page.
        return redirect()->back()
            ->with('success', "Removed '{$name}' as a source for '{$ingredient->name}'.");
    }

    /**
     * Update stock for several ingredients in one submission, either
     * "adjust" (a quantity added to or subtracted from what's on hand) or
     * "recount" (the entered quantity replaces that source's total
     * outright). Each row picks its own source explicitly -- a bulk update
     * is not assumed to be one shopping trip against everyone's usual
     * supplier, the same as the per-ingredient Stock modal lets you touch
     * any source, just batched here for speed across many ingredients at
     * once. An ingredient with no sources at all has nothing to attach a
     * quantity to, so it's skipped rather than updated -- add a real
     * source for it first.
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
        $packagesRule = $request->input('mode') === 'recount'
            ? ['required', 'numeric', 'min:0']
            : ['required', 'numeric'];

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['recount', 'adjust'])],
            // One reason/notes pair for the whole batch, not per row -- a
            // bulk update is normally one shopping trip or one correction
            // pass, so that's the right grain, not forcing the same detail
            // to be typed once per ingredient.
            'reason' => ['required_if:mode,adjust', 'nullable', Rule::in(InventoryAdjustment::USER_SELECTABLE_REASONS)],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'distinct', 'exists:costing_ingredients,id'],
            // Null for an ingredient with no sources at all -- validated
            // for real existence here, but which source it must belong to
            // (and whether it belongs to this row's ingredient at all) is
            // re-checked in the loop below, not assumed from the client.
            'items.*.package_size_id' => ['nullable', 'integer', 'exists:costing_ingredient_package_sizes,id'],
            // Entered in packages (fractional allowed -- half a package
            // used is a real physical count), not the ingredient's raw
            // base unit -- matches how the per-ingredient Stock modal's
            // own Recount already works, and avoids the modal silently
            // expecting a typed-in gram figure with no unit shown.
            'items.*.packages' => $packagesRule,
        ]);

        $reason = $validated['mode'] === 'recount' ? 'recount' : $validated['reason'];

        $skipped = [];
        $clamped = [];

        DB::transaction(function () use ($validated, $reason, $checkIngredientLowStock, $recordInventoryAdjustment, $request, &$skipped, &$clamped) {
            foreach ($validated['items'] as $item) {
                $ingredient = Ingredient::with('inventory', 'packageSizes')->find($item['ingredient_id']);

                $packageSize = $item['package_size_id'] !== null
                    ? $ingredient->packageSizes->firstWhere('id', $item['package_size_id'])
                    : null;

                if ($packageSize === null) {
                    $skipped[] = $ingredient->name;
                    continue;
                }

                // Same packages -> raw-unit conversion adjustSource() does
                // for a single source; here it's just applied per bulk row.
                $quantity = (float) $item['packages'] * (float) $packageSize->package_size;

                $ingredientOldOnHand = (float) $ingredient->inventory->on_hand;
                $sourceOldOnHand = (float) $packageSize->quantity_on_hand;

                if ($validated['mode'] === 'adjust') {
                    $rawNewOnHand = $sourceOldOnHand + $quantity;
                    $sourceNewOnHand = max(0, $rawNewOnHand);
                    if ($rawNewOnHand < 0) {
                        // A subtract larger than what's on hand floors at 0
                        // rather than going negative -- the excess is real
                        // information (the correction was bigger than the
                        // recorded stock), so it's reported, not discarded.
                        $clamped[] = sprintf('%s (by %s)', $ingredient->name, rtrim(rtrim(number_format(abs($rawNewOnHand), 2), '0'), '.'));
                    }
                } else {
                    // Recounts just this source -- not every source for the
                    // ingredient. That "zero every other source" behavior
                    // only made sense back when bulk had no source picker
                    // and always wrote to one auto-resolved target; doing
                    // it now that any source can be explicitly chosen would
                    // silently wipe out real stock sitting on a source the
                    // user never intended to touch in this row.
                    $sourceNewOnHand = $quantity;
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

        if ($clamped !== []) {
            $message .= ' Floored at 0, correction exceeded stock: '.implode(', ', $clamped).'.';
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

    /**
     * A sensible default source to preselect in the bulk modal's per-row
     * picker: the preferred source if one is set and matches, else
     * whichever source happens to be first. The user can still choose a
     * different one -- this is a suggestion, not the write target itself
     * (see bulkUpdate(), which now always uses whatever the request
     * actually specifies). $ingredient must already have packageSizes
     * eager-loaded.
     */
    private function bulkUpdateTargetSource(Ingredient $ingredient): ?PackageSize
    {
        return $ingredient->packageSizes
            ->first(fn (PackageSize $packageSize) => $ingredient->preferred_source
                && $packageSize->provider === $ingredient->preferred_source
                && $packageSize->brand === $ingredient->preferred_brand)
            ?? $ingredient->packageSizes->first();
    }
}
