<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Schedule;
use App\Services\Tenant\ScheduleService;
use App\DTOs\Tenant\CreateScheduleDTO;
use App\DTOs\Tenant\UpdateScheduleDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleService $scheduleService
    ) {}

    /**
     * Display a listing of schedules (calendar data).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Schedule::class);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $schedules = $this->scheduleService->getCalendar(
            $request->get('start_date'),
            $request->get('end_date'),
            $request->get('branch_id') ? (int) $request->get('branch_id') : null
        );

        return response()->json($schedules);
    }

    /**
     * Store a newly created schedule.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Schedule::class);

        $data = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'order_of_service_id' => 'nullable|uuid|exists:orders_of_service,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'assigned_to' => 'nullable|uuid',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $dto = CreateScheduleDTO::fromRequest($data);
        $schedule = $this->scheduleService->store($dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

        return response()->json($schedule, 201);
    }

    /**
     * Display the specified schedule.
     */
    public function show(Schedule $schedule): JsonResponse
    {
        $this->authorize('view', $schedule);

        return response()->json($schedule->load(['customer', 'vehicle', 'branch', 'order']));
    }

    /**
     * Update the specified schedule.
     */
    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorize('update', $schedule);

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'assigned_to' => 'nullable|uuid',
            'status' => ['nullable', 'string', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $dto = UpdateScheduleDTO::fromRequest($data);
        $updated = $this->scheduleService->update($schedule, $dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

        return response()->json($updated);
    }

    /**
     * Remove the specified schedule from storage.
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        $this->authorize('delete', $schedule);

        $schedule->delete();

        return response()->json(null, 204);
    }

    /**
     * Cancel the specified schedule.
     */
    public function cancel(Schedule $schedule): JsonResponse
    {
        $this->authorize('update', $schedule);

        $this->scheduleService->cancel($schedule, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

        return response()->json($schedule->fresh());
    }
}
