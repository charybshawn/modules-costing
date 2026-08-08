<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\ImportKitchenRentalsFromCsv;
use Cultpantry\Costing\Models\KitchenRental;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Support\CostingBreadcrumbs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KitchenRentalController extends Controller implements HasMiddleware
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
        $this->authorize('viewAny', KitchenRental::class);

        $rentals = KitchenRental::with('productionRun')->orderByDesc('starts_at')->get();

        return Inertia::render('Vendor/costing/KitchenRentals/Index', [
            'rentals' => $rentals->map(fn (KitchenRental $rental) => [
                'id' => $rental->id,
                'booking_title' => $rental->booking_title,
                'space_name' => $rental->space_name,
                'venue_name' => $rental->venue_name,
                'status' => $rental->status,
                // Trimmed to 'Y-m-d H:i' to match starts_at/ends_at below,
                // rather than re-parsing through Carbon -- stored values
                // are always 'Y-m-d H:i:s' from Carbon::toDateTimeString().
                'equipment' => collect($rental->equipment ?? [])->map(fn (array $e) => [
                    'name' => $e['name'],
                    'starts_at' => substr($e['starts_at'], 0, 16),
                    'ends_at' => substr($e['ends_at'], 0, 16),
                ])->all(),
                'starts_at' => $rental->starts_at->format('Y-m-d H:i'),
                'ends_at' => $rental->ends_at->format('Y-m-d H:i'),
                'production_run_id' => $rental->production_run_id,
                'production_run_name' => $rental->productionRun?->name ?? $rental->productionRun?->run_date->format('Y-m-d'),
            ]),
            'breadcrumbs' => CostingBreadcrumbs::trail(['label' => 'Rental Schedule']),
        ]);
    }

    public function import(Request $request, ImportKitchenRentalsFromCsv $importKitchenRentalsFromCsv): RedirectResponse
    {
        $this->authorize('import', KitchenRental::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = $importKitchenRentalsFromCsv->handle($validated['file']);

        return redirect()
            ->route('admin.costing.kitchen-rentals.index')
            ->with('success', "Imported {$result['created']} new and updated {$result['updated']} existing rental slots.");
    }

    /**
     * Creates a blank ProductionRun pre-dated to this slot and links it,
     * then hands off to the existing Production Planner edit form to fill
     * in batches -- this is the "build a plan for this rental slot" flow.
     */
    public function createRun(KitchenRental $kitchenRental): RedirectResponse
    {
        $this->authorize('update', $kitchenRental);
        $this->authorize('create', ProductionRun::class);

        $run = ProductionRun::create([
            'name' => $kitchenRental->booking_title,
            'batch_size' => 20,
            'run_date' => $kitchenRental->starts_at->toDateString(),
        ]);

        $kitchenRental->update(['production_run_id' => $run->id]);

        return redirect()
            ->route('admin.costing.production-planner.show', $run)
            ->with('success', "Production run created for '{$kitchenRental->booking_title}'. Fill in batches below.");
    }

    /**
     * Links a slot to a run that was already created separately -- covers
     * planning a run before its matching CSV export exists. No UI calls
     * this yet (a run-picker to choose which existing run to attach is
     * real scope, deliberately deferred) -- detachRun below has a minimal
     * "Unlink Run" row action, but there's no matching "Link Run" one.
     */
    public function attachRun(Request $request, KitchenRental $kitchenRental): RedirectResponse
    {
        $this->authorize('update', $kitchenRental);

        $validated = $request->validate([
            'production_run_id' => ['required', 'exists:costing_production_runs,id'],
        ]);

        $kitchenRental->update(['production_run_id' => $validated['production_run_id']]);

        return redirect()
            ->route('admin.costing.kitchen-rentals.index')
            ->with('success', "Linked '{$kitchenRental->booking_title}' to a production run.");
    }

    public function detachRun(KitchenRental $kitchenRental): RedirectResponse
    {
        $this->authorize('update', $kitchenRental);

        $kitchenRental->update(['production_run_id' => null]);

        return redirect()
            ->route('admin.costing.kitchen-rentals.index')
            ->with('success', "Unlinked '{$kitchenRental->booking_title}' from its production run.");
    }

    /**
     * Manual override for a slot's status -- the CSV export is a snapshot
     * from whenever it was downloaded, so a booking approved/cancelled
     * after that point has no way to reflect the change until re-imported.
     */
    public function updateStatus(Request $request, KitchenRental $kitchenRental): RedirectResponse
    {
        $this->authorize('update', $kitchenRental);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(KitchenRental::STATUSES)],
        ]);

        $kitchenRental->update(['status' => $validated['status']]);

        return redirect()
            ->route('admin.costing.kitchen-rentals.index')
            ->with('success', "Status for '{$kitchenRental->booking_title}' set to {$validated['status']}.");
    }
}
