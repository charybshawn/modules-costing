<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Models\PriceHistoryEntry;
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

    public function index(): Response
    {
        $this->authorize('viewAny', PriceHistoryEntry::class);
        $this->authorize('viewAny', ProductionRun::class);

        $cutoff = now()->subDays(7)->startOfDay();

        // Same "needs an update" definition PriceHistoryController::index()
        // uses: only the latest logged price per ingredient/provider/brand
        // counts, and only if it's stale.
        $stalePriceCount = PriceHistoryEntry::all()
            ->sortByDesc(fn (PriceHistoryEntry $entry) => sprintf('%010d-%010d', $entry->purchased_at?->timestamp ?? 0, $entry->id))
            ->groupBy(fn (PriceHistoryEntry $entry) => $entry->ingredient_id.'||'.$entry->provider.'||'.($entry->brand ?? ''))
            ->map(fn ($group) => $group->first())
            ->filter(fn (PriceHistoryEntry $entry) => $entry->purchased_at === null || $entry->purchased_at->lt($cutoff))
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
