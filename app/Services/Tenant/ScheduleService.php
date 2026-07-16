<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Schedule;
use App\DTOs\Tenant\CreateScheduleDTO;
use App\DTOs\Tenant\UpdateScheduleDTO;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ScheduleService
{
    /**
     * Store a new Schedule with double-booking prevention.
     */
    public function store(CreateScheduleDTO $dto, string $userId): Schedule
    {
        $start = Carbon::parse($dto->start_time);
        $end = Carbon::parse($dto->end_time);

        if ($start->greaterThanOrEqualTo($end)) {
            throw ValidationException::withMessages([
                'start_time' => 'A data/hora de início deve ser anterior à data/hora de término.',
            ]);
        }

        $this->checkDoubleBooking(
            start: $start,
            end: $end,
            assignedTo: $dto->assigned_to,
            vehicleId: $dto->vehicle_id
        );

        return Schedule::create([
            'branch_id' => $dto->branch_id,
            'customer_id' => $dto->customer_id,
            'vehicle_id' => $dto->vehicle_id,
            'order_of_service_id' => $dto->order_of_service_id,
            'title' => $dto->title,
            'description' => $dto->description,
            'start_time' => $start,
            'end_time' => $end,
            'status' => Schedule::STATUS_SCHEDULED,
            'assigned_to' => $dto->assigned_to,
            'notes' => $dto->notes,
            'metadata' => $dto->metadata,
            'created_by' => $userId,
        ]);
    }

    /**
     * Update an existing Schedule.
     */
    public function update(Schedule $schedule, UpdateScheduleDTO $dto, string $userId): Schedule
    {
        $start = $dto->start_time ? Carbon::parse($dto->start_time) : $schedule->start_time;
        $end = $dto->end_time ? Carbon::parse($dto->end_time) : $schedule->end_time;

        if ($start->greaterThanOrEqualTo($end)) {
            throw ValidationException::withMessages([
                'start_time' => 'A data/hora de início deve ser anterior à data/hora de término.',
            ]);
        }

        // Only check double booking if times, mechanic or vehicle is updated
        $hasTimeChanged = $dto->start_time || $dto->end_time;
        $hasAssignedToChanged = $dto->assigned_to !== null && $dto->assigned_to !== $schedule->assigned_to;
        $hasVehicleChanged = $dto->vehicle_id !== null && $dto->vehicle_id !== $schedule->vehicle_id;

        if ($hasTimeChanged || $hasAssignedToChanged || $hasVehicleChanged) {
            $assignedTo = $dto->assigned_to !== null ? $dto->assigned_to : $schedule->assigned_to;
            $vehicleId = $dto->vehicle_id !== null ? $dto->vehicle_id : $schedule->vehicle_id;

            $this->checkDoubleBooking(
                start: $start,
                end: $end,
                assignedTo: $assignedTo,
                vehicleId: $vehicleId,
                excludeId: $schedule->id
            );
        }

        $fields = array_filter([
            'title' => $dto->title,
            'description' => $dto->description,
            'start_time' => $dto->start_time ? $start : null,
            'end_time' => $dto->end_time ? $end : null,
            'vehicle_id' => $dto->vehicle_id,
            'assigned_to' => $dto->assigned_to,
            'status' => $dto->status,
            'notes' => $dto->notes,
            'metadata' => $dto->metadata,
        ], fn($v) => !is_null($v));

        $schedule->update($fields);

        return $schedule->fresh();
    }

    /**
     * Cancel a Schedule.
     */
    public function cancel(Schedule $schedule, string $userId): bool
    {
        return $schedule->update([
            'status' => Schedule::STATUS_CANCELLED,
            'notes' => trim(($schedule->notes ?? '') . "\nAgendamento cancelado em " . now()->toDateTimeString())
        ]);
    }

    /**
     * Get schedules for a calendar range.
     */
    public function getCalendar(string $start, string $end, ?int $branchId = null)
    {
        $query = Schedule::whereBetween('start_time', [
            Carbon::parse($start)->startOfDay(),
            Carbon::parse($end)->endOfDay()
        ])->with(['customer', 'vehicle', 'branch']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('start_time')->get();
    }

    /**
     * Prevent double-booking for vehicles and personnel.
     */
    private function checkDoubleBooking(
        Carbon $start,
        Carbon $end,
        ?string $assignedTo = null,
        ?int $vehicleId = null,
        ?string $excludeId = null
    ): void {
        // Read configuration (defaults to true)
        $preventDoubleBooking = \App\Models\Tenant\Setting::get('prevent_double_booking', true);

        if (!$preventDoubleBooking) {
            return;
        }

        // Query pattern: start_time < $end AND end_time > $start
        $overlappingQuery = fn($query) => $query
            ->where('status', '!=', Schedule::STATUS_CANCELLED)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId));

        // 1. Mechanic Double-booking check
        if ($assignedTo) {
            $hasMechanicConflict = Schedule::where($overlappingQuery)
                ->where('assigned_to', $assignedTo)
                ->exists();

            if ($hasMechanicConflict) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'O funcionário selecionado já possui um agendamento conflitante neste horário.',
                ]);
            }
        }

        // 2. Vehicle Double-booking check
        if ($vehicleId) {
            $hasVehicleConflict = Schedule::where($overlappingQuery)
                ->where('vehicle_id', $vehicleId)
                ->exists();

            if ($hasVehicleConflict) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'O veículo selecionado já possui um agendamento conflitante neste horário.',
                ]);
            }
        }
    }
}
