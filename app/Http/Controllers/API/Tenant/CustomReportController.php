<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CustomReport;
use App\Services\Tenant\ReportBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use App\Jobs\Tenant\RenderCustomReportJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CustomReportController extends Controller
{
    public function __construct(
        protected ReportBuilderService $reportBuilderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomReport::class);

        $reports = CustomReport::paginate(20);

        return response()->json($reports);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomReport::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'model_type' => 'required|string|in:orders,financial,inventory,maintenance',
            'columns' => 'required|array',
            'columns.*' => 'required|string',
            'filters' => 'required|array',
            'group_by' => 'nullable|string',
        ]);

        $validated['created_by'] = (string) $request->user()->id;

        $report = CustomReport::create($validated);

        return response()->json($report, 201);
    }

    public function show(CustomReport $customReport): JsonResponse
    {
        $this->authorize('view', $customReport);

        return response()->json($customReport);
    }

    public function update(Request $request, CustomReport $customReport): JsonResponse
    {
        $this->authorize('update', $customReport);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'model_type' => 'required|string|in:orders,financial,inventory,maintenance',
            'columns' => 'required|array',
            'columns.*' => 'required|string',
            'filters' => 'required|array',
            'group_by' => 'nullable|string',
        ]);

        $customReport->update($validated);

        return response()->json($customReport);
    }

    public function destroy(CustomReport $customReport): JsonResponse
    {
        $this->authorize('delete', $customReport);

        $customReport->delete();

        return response()->json(null, 204);
    }

    public function execute(Request $request, CustomReport $customReport): JsonResponse
    {
        $this->authorize('view', $customReport);

        $runtimeFilters = $request->get('filters', []);
        
        try {
            $data = $this->reportBuilderService->executeQuery($customReport, $runtimeFilters);
            return response()->json([
                'report_name' => $customReport->name,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function export(Request $request, CustomReport $customReport): Response
    {
        $this->authorize('view', $customReport);

        $runtimeFilters = $request->get('filters', []);

        $csv = $this->reportBuilderService->exportToCsv($customReport, $runtimeFilters);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . str_replace(' ', '_', $customReport->name) . '.csv"',
        ]);
    }

    public function queue(Request $request, CustomReport $customReport): JsonResponse
    {
        $this->authorize('view', $customReport);

        $filters = $request->get('filters', []);

        RenderCustomReportJob::dispatch(
            tenant(),
            $customReport,
            $filters
        );

        return response()->json([
            'message' => 'Geração de relatório enfileirada com sucesso em segundo plano.',
        ], 202);
    }

    public function queueStatus(CustomReport $customReport): JsonResponse
    {
        $this->authorize('view', $customReport);

        $status = Cache::get("report_{$customReport->id}_last_rendered");

        if (!$status) {
            return response()->json(['status' => 'pending_or_not_found'], 404);
        }

        return response()->json($status);
    }

    public function downloadQueued(CustomReport $customReport): Response
    {
        $this->authorize('view', $customReport);

        $status = Cache::get("report_{$customReport->id}_last_rendered");

        if (!$status || !Storage::disk('local')->exists($status['path'])) {
            abort(404, 'Arquivo indisponível.');
        }

        $file = Storage::disk('local')->get($status['path']);

        return response($file, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="report_queued_' . $customReport->id . '.csv"',
        ]);
    }
}
