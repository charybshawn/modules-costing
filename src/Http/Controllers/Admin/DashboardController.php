<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\CalculateIngredientCosting;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\ProductionRun;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page for the module -- five entry points matching how the
 * workflow actually runs: two independent weekly tasks (price check,
 * inventory recount) and the event-driven chain a rental slot or a
 * production need kicks off (plan -> purchase -> complete).
 */
class DashboardController extends Controller implements HasMiddleware
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

    public function index(CalculateIngredientCosting $calculateIngredientCosting): Response
    {
        $this->authorize('viewAny', Ingredient::class);
        $this->authorize('viewAny', ProductionRun::class);

        // Same "needs an update" definition the Ingredients page uses: an
        // ingredient counts whether its latest price is stale OR it's never
        // been priced at all -- not just a count of stale PriceHistoryEntry
        // rows, which silently ignored ingredients with zero price history.
        $stalePriceCount = Ingredient::with('priceHistory.ingredient', 'inventory', 'packageSizes')
            ->get()
            ->filter(fn (Ingredient $ingredient) => $calculateIngredientCosting->handle($ingredient)['status'] !== 'ok')
            ->count();

        $plannedRunCount = ProductionRun::whereNull('completed_at')->count();

        return Inertia::render('Vendor/costing/Dashboard/Index', [
            'stale_price_count' => $stalePriceCount,
            'planned_run_count' => $plannedRunCount,
            // Not CostingBreadcrumbs::trail() -- this IS the module root now,
            // so it's a single, non-linked crumb rather than a self-link.
            'breadcrumbs' => [['label' => 'Costing & Recipes']],
        ]);
    }
}
