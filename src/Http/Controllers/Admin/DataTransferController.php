<?php

namespace Cultpantry\Costing\Http\Controllers\Admin;

use App\Actions\GetSiteSetting;
use App\Http\Controllers\Controller;
use Cultpantry\Costing\Actions\ExportCostingData;
use Cultpantry\Costing\Actions\ImportCostingData;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The dashboard's Import / Export: download the module's data (chosen
 * sections) as one JSON file, and bring such a file back in -- previewed
 * first, then merged or wiped-and-replaced on confirmation.
 */
class DataTransferController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless($request->user()?->canAccessAdminPanel(), 403, 'Admin access required.');
                return $next($request);
            }),
            new Middleware(function ($request, $next) {
                abort_unless(app(GetSiteSetting::class)->handle('modules.cultpantry/costing.enabled', true), 404);
                return $next($request);
            }),
        ];
    }

    public function export(Request $request, ExportCostingData $exportCostingData): StreamedResponse
    {
        $this->authorize('viewAny', Ingredient::class);

        $validated = $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [Rule::in(ExportCostingData::SECTIONS)],
        ]);

        $document = $exportCostingData->handle($validated['sections']);
        $filename = 'costing-export-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(
            fn () => print(json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * Reads an uploaded file and reports what importing it would do. Changes
     * nothing: the file is held briefly under a token for import() to apply.
     */
    public function preview(Request $request, ImportCostingData $importCostingData): JsonResponse
    {
        $this->authorizeImport();

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'mode' => ['required', Rule::in(ImportCostingData::MODES)],
        ]);

        try {
            $document = $importCostingData->validateDocument(json_decode((string) file_get_contents($validated['file']->getRealPath()), true));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $sections = $importCostingData->sectionsIn($document);
        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($request, $token), $document, now()->addMinutes(30));

        return response()->json([
            'token' => $token,
            'exported_at' => $document['exported_at'] ?? null,
            'sections' => $sections,
            'preview' => $importCostingData->preview($document, $sections, $validated['mode']),
        ]);
    }

    /** Previews again for a different section choice or mode, without re-uploading. */
    public function repreview(Request $request, ImportCostingData $importCostingData): JsonResponse
    {
        $this->authorizeImport();

        $validated = $this->validatedImport($request);
        $document = Cache::get($this->cacheKey($request, $validated['token']));
        abort_if($document === null, 410, 'That upload has expired -- choose the file again.');

        return response()->json([
            'preview' => $importCostingData->preview($document, $validated['sections'], $validated['mode']),
        ]);
    }

    public function import(Request $request, ImportCostingData $importCostingData): RedirectResponse
    {
        $this->authorizeImport();

        $validated = $this->validatedImport($request);
        $key = $this->cacheKey($request, $validated['token']);
        $document = Cache::get($key);
        if ($document === null) {
            return redirect()->back()->with('error', 'That upload has expired -- choose the file again.');
        }

        $summary = $importCostingData->apply($document, $validated['sections'], $validated['mode'], $request->user()?->id);
        Cache::forget($key);

        $parts = collect($summary)->map(function (array $counts, string $section) {
            $label = str_replace('_', ' ', $section);
            $bits = array_filter([
                $counts['deleted'] ? "{$counts['deleted']} removed" : null,
                $counts['created'] ? "{$counts['created']} added" : null,
                $counts['updated'] ? "{$counts['updated']} updated" : null,
                $counts['skipped'] ? "{$counts['skipped']} skipped" : null,
            ]);

            return ucfirst($label).': '.($bits ? implode(', ', $bits) : 'no changes');
        })->implode('; ');

        return redirect()->back()->with('success', "Import complete. {$parts}.");
    }

    private function authorizeImport(): void
    {
        $this->authorize('create', Ingredient::class);
        $this->authorize('create', Recipe::class);
        $this->authorize('create', ProductionRun::class);
    }

    private function validatedImport(Request $request): array
    {
        return $request->validate([
            'token' => ['required', 'uuid'],
            'mode' => ['required', Rule::in(ImportCostingData::MODES)],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [Rule::in(ExportCostingData::SECTIONS)],
        ]);
    }

    /** Scoped to the uploader, so one admin can't apply another's upload. */
    private function cacheKey(Request $request, string $token): string
    {
        return 'costing-import:'.$request->user()?->id.':'.$token;
    }
}
