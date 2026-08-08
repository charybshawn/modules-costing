<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\CalculateProductionPlan;
use Cultpantry\Costing\Actions\CompleteProductionRun;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
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

    public function index(CalculateProductionPlan $calculateProductionPlan): Response
    {
        $this->authorize('viewAny', ProductionRun::class);

        $recipes = Recipe::orderBy('name')->get(['id', 'name']);
        $latestRun = ProductionRun::with('recipes')->latest('run_date')->latest('id')->first();

        return Inertia::render('Vendor/costing/ProductionPlanner/Index', [
            'recipes' => $recipes,
            'production_run' => $latestRun ? $this->serializeRun($latestRun) : null,
            'plan' => $latestRun ? $calculateProductionPlan->handle($latestRun) : null,
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Production Planner']),
        ]);
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
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Production Planner', 'href' => route('admin.costing.production-planner.index')],
                ['label' => 'All Runs'],
            ),
        ]);
    }

    /**
     * A genuinely blank run -- unlike index(), which always preloads the
     * latest run (so it can act as a "continue where I left off"
     * dashboard), this is the only route that hands Index.vue a null
     * production_run once at least one run already exists, which is what
     * puts its form/submit() into create mode instead of edit mode.
     */
    public function create(): Response
    {
        $this->authorize('create', ProductionRun::class);

        $recipes = Recipe::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Vendor/costing/ProductionPlanner/Index', [
            'recipes' => $recipes,
            'production_run' => null,
            'plan' => null,
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Production Planner', 'href' => route('admin.costing.production-planner.index')],
                ['label' => 'New Run'],
            ),
        ]);
    }

    /**
     * View (and edit) a specific past run -- reuses the same Index page the
     * "latest run" view uses, since its form already creates-vs-updates
     * based on whether a production_run prop is present.
     */
    public function show(ProductionRun $productionRun, CalculateProductionPlan $calculateProductionPlan): Response
    {
        $this->authorize('view', $productionRun);

        $recipes = Recipe::orderBy('name')->get(['id', 'name']);
        $productionRun->load('recipes');

        return Inertia::render('Vendor/costing/ProductionPlanner/Index', [
            'recipes' => $recipes,
            'production_run' => $this->serializeRun($productionRun),
            'plan' => $calculateProductionPlan->handle($productionRun),
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Production Planner', 'href' => route('admin.costing.production-planner.index')],
                ['label' => $productionRun->name ?? $productionRun->run_date->format('Y-m-d')],
            ),
        ]);
    }

    public function store(Request $request, CalculateProductionPlan $calculateProductionPlan): RedirectResponse
    {
        $this->authorize('create', ProductionRun::class);

        $validated = $this->validated($request);

        $run = ProductionRun::create([
            'name' => $validated['name'] ?? null,
            'batch_size' => $validated['batch_size'],
            'run_date' => $validated['run_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $run->recipes()->sync($this->syncData($validated['batches']));

        return redirect()
            ->route('admin.costing.production-planner.index')
            ->with('success', 'Production run created.');
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

        // usePersistedForm's autosave also PUTs here while the user is
        // still on this run's page and needs to stay put -- the index
        // route redirects to whichever run is *latest*, which may not be
        // this one, making the normal redirect actively wrong here, not
        // just disruptive. Same "stay" pattern as the other controllers.
        if ($request->boolean('stay')) {
            return redirect()->back()->with('success', 'Production run updated.');
        }

        return redirect()
            ->route('admin.costing.production-planner.index')
            ->with('success', 'Production run updated.');
    }

    /**
     * Mark a run complete and draw its required quantities down from
     * Inventory. Idempotent -- a run can only be completed once.
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

        return redirect()
            ->route('admin.costing.production-planner.show', $productionRun)
            ->with($shortfalls === [] ? 'success' : 'warning', $message);
    }

    /**
     * A completed run has already drawn down real Inventory and owns
     * cascade-deleted RecipeCostSnapshot rows (see CompleteProductionRun) --
     * deleting it afterward would silently erase that cost history, so it's
     * blocked here the same way editing already is in update() above.
     */
    public function destroy(ProductionRun $productionRun): RedirectResponse
    {
        $this->authorize('delete', $productionRun);

        abort_if($productionRun->completed_at, 422, 'A completed run cannot be deleted -- its inventory deduction and cost history are historical fact.');

        $name = $productionRun->name ?? $productionRun->run_date->format('Y-m-d');
        $productionRun->delete();

        return redirect()
            ->route('admin.costing.production-planner.runs')
            ->with('success', "Production run '{$name}' deleted.");
    }

    /**
     * Print-friendly view showing only ingredients that need purchasing --
     * replaces the original Purchase Order tab.
     */
    public function purchaseOrder(ProductionRun $productionRun, CalculateProductionPlan $calculateProductionPlan): Response
    {
        $this->authorize('view', $productionRun);

        $plan = $calculateProductionPlan->handle($productionRun);

        return Inertia::render('Vendor/costing/ProductionPlanner/PurchaseOrder', [
            'production_run' => $this->serializeRun($productionRun),
            'plan' => $plan,
            'breadcrumbs' => CostingBreadcrumbs::trail(
                ['label' => 'Production Planner', 'href' => route('admin.costing.production-planner.index')],
                ['label' => $productionRun->name ?? $productionRun->run_date->format('Y-m-d'), 'href' => route('admin.costing.production-planner.show', $productionRun)],
                ['label' => 'Purchase Order'],
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
