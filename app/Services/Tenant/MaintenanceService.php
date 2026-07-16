<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Vehicle;
use App\Models\Tenant\PreventiveRule;
use App\Models\Tenant\MaintenanceLog;
use App\Models\Tenant\VehiclePreventiveRuleStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaintenanceService
{
    public function createLog(array $data, string $userId): MaintenanceLog
    {
        return DB::connection('tenant')->transaction(function () use ($data, $userId) {
            $log = MaintenanceLog::create([
                'vehicle_id' => $data['vehicle_id'],
                'preventive_rule_id' => $data['preventive_rule_id'] ?? null,
                'order_of_service_id' => $data['order_of_service_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'status' => MaintenanceLog::STATUS_SCHEDULED,
                'scheduled_date' => $data['scheduled_date'],
                'created_by' => $userId,
            ]);

            return $log;
        });
    }

    public function updateLog(MaintenanceLog $log, array $data): MaintenanceLog
    {
        if ($log->status === MaintenanceLog::STATUS_COMPLETED || $log->status === MaintenanceLog::STATUS_CANCELLED) {
            throw new \Exception('Não é possível editar uma manutenção concluída ou cancelada.');
        }

        $log->update(array_filter([
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'preventive_rule_id' => $data['preventive_rule_id'] ?? null,
            'order_of_service_id' => $data['order_of_service_id'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? null,
        ]));

        return $log;
    }

    public function completeLog(MaintenanceLog $log, int $currentOdometer, ?float $cost, string $userId): MaintenanceLog
    {
        return DB::connection('tenant')->transaction(function () use ($log, $currentOdometer, $cost, $userId) {
            if ($log->status === MaintenanceLog::STATUS_COMPLETED || $log->status === MaintenanceLog::STATUS_CANCELLED) {
                throw new \Exception('Esta manutenção já foi finalizada ou cancelada.');
            }

            $log->update([
                'status' => MaintenanceLog::STATUS_COMPLETED,
                'completed_date' => now(),
                'odometer' => $currentOdometer,
                'cost' => $cost ?? $log->cost ?? 0.0,
            ]);

            $vehicle = $log->vehicle;
            if ($currentOdometer > $vehicle->odometer) {
                $vehicle->update(['odometer' => $currentOdometer]);
            }

            if ($log->preventive_rule_id) {
                $rule = PreventiveRule::find($log->preventive_rule_id);
                if ($rule) {
                    $this->calculateRuleStatusForVehicle($vehicle, $rule);
                }
            }

            return $log->load(['vehicle', 'preventiveRule']);
        });
    }

    public function calculateRuleStatusForVehicle(Vehicle $vehicle, PreventiveRule $rule): VehiclePreventiveRuleStatus
    {
        $latestLog = MaintenanceLog::where('vehicle_id', $vehicle->id)
            ->where('preventive_rule_id', $rule->id)
            ->where('status', MaintenanceLog::STATUS_COMPLETED)
            ->latest('completed_date')
            ->latest('created_at')
            ->first();

        if ($latestLog) {
            $lastKms = (int) $latestLog->odometer;
            $lastDate = $latestLog->completed_date;
        } else {
            $lastKms = 0;
            $lastDate = null;
        }

        $nextKms = ($latestLog ? $lastKms : (int) $vehicle->odometer) + $rule->interval_kms;
        $nextDate = ($lastDate ? $lastDate->copy() : now())->addDays($rule->interval_days);

        return VehiclePreventiveRuleStatus::updateOrCreate(
            [
                'vehicle_id' => $vehicle->id,
                'preventive_rule_id' => $rule->id,
            ],
            [
                'last_performed_kms' => $lastKms,
                'last_performed_date' => $lastDate,
                'next_due_kms' => $nextKms,
                'next_due_date' => $nextDate,
            ]
        );
    }

    public function getDueAlerts(Vehicle $vehicle): array
    {
        // First, guarantee all active rules have statuses calculated
        $rules = PreventiveRule::where('is_active', true)
            ->where(function ($q) use ($vehicle) {
                $q->whereNull('vehicle_id')
                  ->orWhere('vehicle_id', $vehicle->id);
            })->get();

        foreach ($rules as $rule) {
            $status = VehiclePreventiveRuleStatus::where('vehicle_id', $vehicle->id)
                ->where('preventive_rule_id', $rule->id)
                ->first();
            if (!$status) {
                $this->calculateRuleStatusForVehicle($vehicle, $rule);
            }
        }

        $statuses = VehiclePreventiveRuleStatus::with('preventiveRule')
            ->where('vehicle_id', $vehicle->id)
            ->get();

        $alerts = [];
        $today = now()->startOfDay();

        foreach ($statuses as $status) {
            $rule = $status->preventiveRule;
            if (!$rule || !$rule->is_active) {
                continue;
            }

            $kmsRemaining = $status->next_due_kms - (int) $vehicle->odometer;
            $daysRemaining = $status->next_due_date ? $today->diffInDays($status->next_due_date, false) : null;

            $dueByKms = $kmsRemaining <= 1000;
            $dueByDate = $daysRemaining !== null && $daysRemaining <= 15;

            if ($dueByKms || $dueByDate) {
                $alerts[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'next_due_kms' => $status->next_due_kms,
                    'next_due_date' => $status->next_due_date ? $status->next_due_date->toDateString() : null,
                    'kms_remaining' => $kmsRemaining,
                    'days_remaining' => $daysRemaining,
                    'reason' => ($dueByKms && $dueByDate) ? 'kms_and_date' : ($dueByKms ? 'kms' : 'date'),
                ];
            }
        }

        return $alerts;
    }

    public function getCostAnalysis(Vehicle $vehicle): array
    {
        $completedLogs = MaintenanceLog::where('vehicle_id', $vehicle->id)
            ->where('status', MaintenanceLog::STATUS_COMPLETED);

        $totalCost = (float) $completedLogs->sum('cost');
        $averageCost = (float) $completedLogs->avg('cost');

        $byType = MaintenanceLog::where('vehicle_id', $vehicle->id)
            ->where('status', MaintenanceLog::STATUS_COMPLETED)
            ->select('type', DB::raw('SUM(cost) as total_cost'))
            ->groupBy('type')
            ->get()
            ->pluck('total_cost', 'type')
            ->map(fn($val) => (float)$val)
            ->toArray();

        return [
            'total_cost' => $totalCost,
            'average_cost' => $averageCost,
            'by_type' => $byType,
        ];
    }
}
