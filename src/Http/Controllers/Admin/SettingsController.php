<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Actions\UpdateSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\GetBatchCodePrefix;
use Cultpantry\Costing\Actions\GetPriceStalenessDays;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Module-scoped settings -- not the host app's site-wide Settings page.
 * Storage reuses the app's generic Setting key/value table (same mechanism
 * the "modules.cultpantry/costing.enabled" toggle already uses) under a
 * costing-namespaced key, but this page is the only place that reads or
 * writes it, and it never appears on Admin\SettingsController's own page.
 */
class SettingsController extends Controller implements HasMiddleware
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

    public function index(GetPriceStalenessDays $getPriceStalenessDays, GetBatchCodePrefix $getBatchCodePrefix): Response
    {
        return Inertia::render('Vendor/costing/Settings/Index', [
            'staleness_days' => $getPriceStalenessDays->handle(),
            'batch_code_prefix' => $getBatchCodePrefix->handle(),
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Settings']),
        ]);
    }

    public function update(Request $request, UpdateSiteSetting $updateSetting): RedirectResponse
    {
        $validated = $request->validate([
            'staleness_days' => ['required', 'integer', 'min:1', 'max:90'],
            'batch_code_prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
        ]);

        $updateSetting->handle(GetPriceStalenessDays::SETTING_KEY, $validated['staleness_days']);
        $updateSetting->handle(GetBatchCodePrefix::SETTING_KEY, strtoupper($validated['batch_code_prefix'] ?? ''));

        return back()->with('success', 'Settings updated.');
    }
}
