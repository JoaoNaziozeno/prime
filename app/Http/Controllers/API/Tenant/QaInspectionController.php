<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\QaInspection;
use App\Models\Tenant\QaDefect;
use App\Models\Tenant\OrderOfService;
use App\Services\Tenant\QualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QaInspectionController extends Controller
{
    public function __construct(
        protected QualityService $qualityService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', QaInspection::class);

        $query = QaInspection::with(['template', 'orderOfService']);

        if ($request->has('order_of_service_id')) {
            $query->where('order_of_service_id', $request->get('order_of_service_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $inspections = $query->paginate(20);

        return response()->json($inspections);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', QaInspection::class);

        $validated = $request->validate([
            'order_of_service_id' => 'required|exists:tenant.orders_of_service,id',
            'qa_template_id' => 'nullable|exists:tenant.qa_templates,id',
        ]);

        $order = OrderOfService::findOrFail($validated['order_of_service_id']);

        $inspection = $this->qualityService->createInspection(
            $order,
            $validated['qa_template_id'] ?? null,
            (string) $request->user()->id
        );

        return response()->json($inspection, 201);
    }

    public function show(QaInspection $qaInspection): JsonResponse
    {
        $this->authorize('view', $qaInspection);

        return response()->json($qaInspection->load(['template', 'defects', 'orderOfService']));
    }

    public function updateItems(Request $request, QaInspection $qaInspection): JsonResponse
    {
        $this->authorize('update', $qaInspection);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.name' => 'required|string',
            'items.*.status' => 'required|string|in:pending,checked,failed',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            $inspection = $this->qualityService->updateInspectionItems($qaInspection, $validated['items']);
            return response()->json($inspection);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function complete(Request $request, QaInspection $qaInspection): JsonResponse
    {
        $this->authorize('update', $qaInspection);

        $validated = $request->validate([
            'status' => 'required|string|in:passed,failed',
            'notes' => 'nullable|string',
        ]);

        try {
            $inspection = $this->qualityService->completeInspection($qaInspection, $validated['status'], $validated['notes'] ?? null);
            return response()->json($inspection);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function logDefect(Request $request, QaInspection $qaInspection): JsonResponse
    {
        $this->authorize('update', $qaInspection);

        $validated = $request->validate([
            'description' => 'required|string',
            'severity' => 'required|string|in:low,medium,high,critical',
        ]);

        try {
            $defect = $this->qualityService->logDefect($qaInspection, $validated['description'], $validated['severity']);
            return response()->json($defect, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function resolveDefect(Request $request, QaDefect $defect): JsonResponse
    {
        // Require standard user to be staff
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        try {
            $defect = $this->qualityService->resolveDefect($defect, (string) $request->user()->id);
            return response()->json($defect);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
