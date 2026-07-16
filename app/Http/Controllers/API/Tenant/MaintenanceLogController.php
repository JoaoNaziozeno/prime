<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MaintenanceLog;
use App\Models\Tenant\Vehicle;
use App\Services\Tenant\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceLogController extends Controller
{
    public function __construct(
        protected MaintenanceService $maintenanceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MaintenanceLog::class);

        $query = MaintenanceLog::with(['vehicle', 'preventiveRule']);

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->get('vehicle_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $logs = $query->paginate(20);

        return response()->json($logs);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', MaintenanceLog::class);

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:tenant.vehicles,id',
            'preventive_rule_id' => 'nullable|exists:tenant.preventive_rules,id',
            'order_of_service_id' => 'nullable|exists:tenant.orders_of_service,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:preventive,corrective,predictive',
            'scheduled_date' => 'required|date',
        ]);

        $log = $this->maintenanceService->createLog($validated, (string) $request->user()->id);

        return response()->json($log, 201);
    }

    public function show(MaintenanceLog $maintenanceLog): JsonResponse
    {
        $this->authorize('view', $maintenanceLog);

        return response()->json($maintenanceLog->load(['vehicle', 'preventiveRule']));
    }

    public function update(Request $request, MaintenanceLog $maintenanceLog): JsonResponse
    {
        $this->authorize('update', $maintenanceLog);

        $validated = $request->validate([
            'vehicle_id' => 'nullable|exists:tenant.vehicles,id',
            'preventive_rule_id' => 'nullable|exists:tenant.preventive_rules,id',
            'order_of_service_id' => 'nullable|exists:tenant.orders_of_service,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|in:preventive,corrective,predictive',
            'status' => 'nullable|string|in:scheduled,in_progress,completed,cancelled',
            'scheduled_date' => 'nullable|date',
        ]);

        $log = $this->maintenanceService->updateLog($maintenanceLog, $validated);

        return response()->json($log);
    }

    public function destroy(MaintenanceLog $maintenanceLog): JsonResponse
    {
        $this->authorize('delete', $maintenanceLog);

        $maintenanceLog->delete();

        return response()->json(null, 204);
    }

    public function complete(Request $request, MaintenanceLog $maintenanceLog): JsonResponse
    {
        $this->authorize('update', $maintenanceLog);

        $validated = $request->validate([
            'odometer' => 'required|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $log = $this->maintenanceService->completeLog(
            $maintenanceLog,
            $validated['odometer'],
            $validated['cost'] ?? null,
            (string) $request->user()->id
        );

        return response()->json([
            'message' => 'Manutenção concluída com sucesso.',
            'maintenance_log' => $log,
        ]);
    }

    public function vehicleStatus(Vehicle $vehicle): JsonResponse
    {
        $alerts = $this->maintenanceService->getDueAlerts($vehicle);

        return response()->json([
            'vehicle_id' => $vehicle->id,
            'current_odometer' => $vehicle->odometer,
            'alerts' => $alerts,
        ]);
    }

    public function costAnalysis(Vehicle $vehicle): JsonResponse
    {
        $analysis = $this->maintenanceService->getCostAnalysis($vehicle);

        return response()->json([
            'vehicle_id' => $vehicle->id,
            'cost_analysis' => $analysis,
        ]);
    }
}
