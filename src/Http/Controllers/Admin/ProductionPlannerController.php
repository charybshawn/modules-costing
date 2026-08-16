<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\CalculateProductionPlan;
use Cultpantry\Costing\Actions\CompleteProductionRun;
use Cultpantry\Costing\Actions\UncompleteProductionRun;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ProductionPlannerController extends Controller implements HasMiddleware
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

    /**
     * List all production runs, newest first -- the sheet only ever had one
     * "current" state, so this is new functionality rather than a port.
     */
    public function runs(): Response
    {
        $this->authorize('viewAny', ProductionRun::class);

        $runs = ProductionRun::with('recipes')->orderByDesc('run_date')->orderByDesc('id')->get();

        return Inertia::render('Vendor/costing/ProductionPlanner/Runs', [
            'runs' => $runs->map(fn (ProductionRun $run) => [
                'id' => $run->id,
                'name' => $run->name,
                'run_date' => $run->run_date->format('Y-m-d'),
                'total_units' => $run->totalUnits(),
                'completed_at' => optional($run->completed_at)->format('Y-m-d H:i'),
            ]),
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'All Runs']),
        ]);
    }

    /**
     * Run + plan data for ProductionPlanModal.vue -- there's no standalone
     * page for a run anymore (planning is driven entirely from a Rental
     * Schedule slot or the All Runs history list, both of which open this
     * as a modal), so this returns JSON rather than an Inertia page, same
     * pattern as InventoryController::sources() for StockAdjustModal.
     */
    public function show(ProductionRun $productionRun, CalculateProductionPlan $calculateProductionPlan): JsonResponse
    {
        $this->authorize('view', $productionRun);

        $recipes = Recipe::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'recipes' => $recipes,
            'production_run' => $this->serializeRun($productionRun),
            // Not computed for a completed run -- its shopping list is moot
            // (nothing left to purchase for something already made) and the
            // modal doesn't render one, so skip the live pricing/inventory
            // query entirely rather than compute and discard it.
            'plan' => $productionRun->completed_at ? null : $calculateProductionPlan->handle($productionRun),
        ]);
    }

    public function update(Request $request, ProductionRun $productionRun): RedirectResponse
    {
        $this->authorize('update', $productionRun);

        // A completed run's batch counts (and batch size) are historical
        // fact tied to an actual inventory deduction (see complete()) --
        // editing them afterward would silently desync the two.
        abort_if($productionRun->completed_at, 422, 'This run has already been completed and can no longer be edited.');

        $validated = $this->validated($request);

        $productionRun->update([
            'name' => $validated['name'] ?? null,
            'batch_size' => $validated['batch_size'],
            'run_date' => $validated['run_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $productionRun->recipes()->sync($this->syncData($validated['batches']));

        // Always back, never a page redirect -- this run is only ever
        // edited from ProductionPlanModal.vue, layered over whichever page
        // opened it (Rental Schedule, All Runs, or Inventory's adjustment
        // log), so there's no standalone "the run's page" to redirect to
        // anymore. usePersistedForm's autosave also PUTs here while the
        // modal is open and needs the underlying page to stay put.
        return redirect()->back()->with('success', 'Production run updated.');
    }

    /**
     * Mark a run complete and draw its required quantities down from
     * Inventory. Idempotent -- a run can only be completed once (see
     * CompleteProductionRun's own locked re-check for the actual guarantee
     * against a race, not just this pre-check).
     *
     * Ingredient deduction always uses the *planned* quantities (see
     * CompleteProductionRun) -- actual units produced can differ from the
     * plan (e.g. batches running heavier than assumed) without any change
     * in ingredients consumed, so it's recorded separately here purely for
     * historical/costing purposes, not fed back into the deduction math.
     */
    public function complete(Request $request, ProductionRun $productionRun, CompleteProductionRun $completeProductionRun): RedirectResponse
    {
        $this->authorize('complete', $productionRun);

        abort_if($productionRun->completed_at, 422, 'This run has already been completed.');

        $validated = $request->validate([
            'actuals' => ['array'],
            'actuals.*.recipe_id' => ['required', 'integer'],
            'actuals.*.actual_units' => ['nullable', 'integer', 'min:0'],
        ]);

        $actuals = collect($validated['actuals'] ?? [])
            ->filter(fn (array $row) => $row['actual_units'] !== null)
            ->pluck('actual_units', 'recipe_id')
            ->all();

        $shortfalls = $completeProductionRun->handle($productionRun, $actuals);

        $message = 'Production run completed. Inventory has been updated.';
        if ($shortfalls !== []) {
            $message .= ' Ran short on: '.implode(', ', $shortfalls).'.';
        }

        return redirect()->back()->with($shortfalls === [] ? 'success' : 'warning', $message);
    }

    /**
     * Reverses complete() -- credits back the ingredient quantities it
     * deducted (via the InventoryAdjustment audit trail, see
     * UncompleteProductionRun), deletes the cost snapshots it froze, and
     * returns the run to "Planned" so it's editable and re-completable.
     */
    public function uncomplete(ProductionRun $productionRun, UncompleteProductionRun $uncompleteProductionRun): RedirectResponse
    {
        $this->authorize('uncomplete', $productionRun);

        abort_unless($productionRun->completed_at, 422, 'This run has not been completed.');

        $warnings = $uncompleteProductionRun->handle($productionRun);

        $message = 'Production run completion undone. Inventory has been restored.';
        if ($warnings !== []) {
            $message .= ' Could not restore: '.implode(', ', $warnings).'.';
        }

        return redirect()->back()->with($warnings === [] ? 'success' : 'warning', $message);
    }

    /**
     * A completed run has already drawn down real Inventory and owns
     * cascade-deleted RecipeCostSnapshot rows (see CompleteProductionRun) --
     * deleting it afterward would silently erase that cost history, so it's
     * blocked here the same way editing already is in update() above. Undo
     * the completion first (uncomplete()) if the run needs to go away.
     */
    public function destroy(ProductionRun $productionRun): RedirectResponse
    {
        $this->authorize('delete', $productionRun);

        abort_if($productionRun->completed_at, 422, 'A completed run cannot be deleted -- undo its completion first.');

        $name = $productionRun->name ?? $productionRun->run_date->format('Y-m-d');
        $productionRun->delete();

        return redirect()
            ->route('admin.costing.production-planner.runs')
            ->with('success', "Production run '{$name}' deleted.");
    }

    /**
     * Print-friendly view showing only ingredients that need purchasing --
     * replaces the original Purchase Order tab. Stays a real page (unlike
     * show() above) since it's meant to be printed.
     */
    public function purchaseOrder(ProductionRun $productionRun, CalculateProductionPlan $calculateProductionPlan): Response
    {
        $this->authorize('view', $productionRun);

        $plan = $calculateProductionPlan->handle($productionRun);

        return Inertia::render('Vendor/costing/ProductionPlanner/PurchaseOrder', [
            'production_run' => $this->serializeRun($productionRun),
            'plan' => $plan,
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'All Runs', 'href' => route('admin.costing.production-planner.runs')],
                ['label' => ($productionRun->name ?? $productionRun->run_date->format('Y-m-d')).' -- Purchase Order'],
            ),
        ]);
    }

    private function serializeRun(ProductionRun $productionRun): array
    {
        $productionRun->loadMissing('recipes');

        return [
            'id' => $productionRun->id,
            'name' => $productionRun->name,
            'batch_size' => $productionRun->batch_size,
            'run_date' => $productionRun->run_date->format('Y-m-d'),
            'notes' => $productionRun->notes,
            'completed_at' => optional($productionRun->completed_at)->format('Y-m-d H:i'),
            'total_units' => $productionRun->totalUnits(),
            'batches' => $productionRun->recipes->map(fn (Recipe $recipe) => [
                'recipe_id' => $recipe->id,
                'recipe_name' => $recipe->name,
                'batches' => (int) $recipe->pivot->batches,
                'actual_units' => $recipe->pivot->actual_units !== null ? (int) $recipe->pivot->actual_units : null,
            ]),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'batch_size' => ['required', 'integer', 'min:1'],
            'run_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'batches' => ['array'],
            'batches.*.recipe_id' => ['required', 'exists:costing_recipes,id'],
            'batches.*.batches' => ['required', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param array<int, array{recipe_id: int, batches: int}> $batches
     * @return array<int, array{batches: int}>
     */
    private function syncData(array $batches): array
    {
        $syncData = [];
        foreach ($batches as $row) {
            $syncData[$row['recipe_id']] = ['batches' => $row['batches']];
        }

        return $syncData;
    }
}
