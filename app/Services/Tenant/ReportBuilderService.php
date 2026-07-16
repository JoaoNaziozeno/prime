<?php

namespace App\Services\Tenant;

use App\Models\Tenant\CustomReport;
use App\Models\Tenant\ScheduledReport;
use App\Models\Tenant\DailyMetricsSnapshot;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Product;
use App\Models\Tenant\MaintenanceLog;
use App\Models\Tenant\OrderFeedback;
use App\Models\Tenant\QaDefect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportBuilderService
{
    protected array $modelMapping = [
        CustomReport::TYPE_ORDERS => OrderOfService::class,
        CustomReport::TYPE_FINANCIAL => Invoice::class,
        CustomReport::TYPE_INVENTORY => Product::class,
        CustomReport::TYPE_MAINTENANCE => MaintenanceLog::class,
    ];

    public function executeQuery(CustomReport $report, array $runtimeFilters = []): \Illuminate\Support\Collection
    {
        $modelClass = $this->modelMapping[$report->model_type] ?? null;

        if (!$modelClass) {
            throw new \Exception("Tipo de relatório inválido.");
        }

        $query = $modelClass::query();

        // Apply selected columns
        $columns = $report->columns;
        if (!empty($columns)) {
            // Keep ID or key fields if needed
            $query->select($columns);
        }

        // Apply filters
        $filters = array_merge($report->filters ?? [], $runtimeFilters);

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'status') {
                $query->where('status', $value);
            } elseif ($key === 'start_date') {
                $dateCol = $report->model_type === CustomReport::TYPE_FINANCIAL ? 'issue_date' : 'created_at';
                $query->where($dateCol, '>=', Carbon::parse($value)->startOfDay());
            } elseif ($key === 'end_date') {
                $dateCol = $report->model_type === CustomReport::TYPE_FINANCIAL ? 'issue_date' : 'created_at';
                $query->where($dateCol, '<=', Carbon::parse($value)->endOfDay());
            } elseif (in_array($key, (new $modelClass)->getFillable())) {
                $query->where($key, $value);
            }
        }

        // Apply group by
        if ($report->group_by) {
            $query->groupBy($report->group_by);
        }

        return $query->get();
    }

    public function exportToCsv(CustomReport $report, array $runtimeFilters = []): string
    {
        $results = $this->executeQuery($report, $runtimeFilters);

        if ($results->isEmpty()) {
            return "Nenhum dado encontrado para os filtros aplicados.\n";
        }

        $handle = fopen('php://temp', 'r+');

        // Headers
        $firstItem = $results->first()->toArray();
        fputcsv($handle, array_keys($firstItem));

        // Data lines
        foreach ($results as $row) {
            fputcsv($handle, array_values($row->toArray()));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function generateDailySnapshot(string $date): DailyMetricsSnapshot
    {
        return DB::connection('tenant')->transaction(function () use ($date) {
            $carbonDate = Carbon::parse($date)->toDateString();
            $start = Carbon::parse($date)->startOfDay();
            $end = Carbon::parse($date)->endOfDay();

            // Total revenue (paid invoices created/paid today)
            $revenue = (float) Invoice::whereBetween('created_at', [$start, $end])
                ->where('status', Invoice::STATUS_PAID)
                ->sum('total_amount');

            // Total cost (actual cost of completed orders today)
            $cost = (float) OrderOfService::whereBetween('actual_end_date', [$start, $end])
                ->where('status', OrderOfService::STATUS_COMPLETED)
                ->sum('actual_cost');

            $margin = $revenue - $cost;

            $ordersCreated = OrderOfService::whereBetween('created_at', [$start, $end])->count();
            
            $ordersCompleted = OrderOfService::whereBetween('actual_end_date', [$start, $end])
                ->where('status', OrderOfService::STATUS_COMPLETED)
                ->count();

            $npsAverage = (float) (OrderFeedback::whereBetween('created_at', [$start, $end])->avg('nps_score') ?? 0.00);
            
            $defectsCount = QaDefect::whereBetween('created_at', [$start, $end])->count();

            return DailyMetricsSnapshot::updateOrCreate(
                ['snapshot_date' => $carbonDate],
                [
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'margin' => $margin,
                    'orders_created' => $ordersCreated,
                    'orders_completed' => $ordersCompleted,
                    'nps_average' => $npsAverage,
                    'defects_count' => $defectsCount,
                ]
            );
        });
    }

    public function getBiDataRange(string $startDate, string $endDate): array
    {
        $snapshots = DailyMetricsSnapshot::whereBetween('snapshot_date', [$startDate, $endDate])
            ->orderBy('snapshot_date')
            ->get();

        $labels = [];
        $revenue = [];
        $cost = [];
        $margin = [];
        $nps = [];
        $ordersCompleted = [];

        foreach ($snapshots as $snapshot) {
            $labels[] = $snapshot->snapshot_date->toDateString();
            $revenue[] = $snapshot->revenue;
            $cost[] = $snapshot->cost;
            $margin[] = $snapshot->margin;
            $nps[] = $snapshot->nps_average;
            $ordersCompleted[] = $snapshot->orders_completed;
        }

        return [
            'labels' => $labels,
            'series' => [
                'revenue' => $revenue,
                'cost' => $cost,
                'margin' => $margin,
                'nps' => $nps,
                'orders_completed' => $ordersCompleted,
            ]
        ];
    }

    public function processScheduledReports(): void
    {
        $scheduled = ScheduledReport::where('is_active', true)->get();
        $now = now();

        foreach ($scheduled as $job) {
            $lastSent = $job->last_sent_at;
            $shouldSend = false;

            if (!$lastSent) {
                $shouldSend = true;
            } else {
                if ($job->frequency === ScheduledReport::FREQUENCY_DAILY && $lastSent->diffInHours($now) >= 24) {
                    $shouldSend = true;
                } elseif ($job->frequency === ScheduledReport::FREQUENCY_WEEKLY && $lastSent->diffInDays($now) >= 7) {
                    $shouldSend = true;
                } elseif ($job->frequency === ScheduledReport::FREQUENCY_MONTHLY && $lastSent->diffInDays($now) >= 30) {
                    $shouldSend = true;
                }
            }

            if ($shouldSend) {
                try {
                    $csv = $this->exportToCsv($job->customReport);

                    // Mock sending email notification
                    Log::info("Scheduled report '{$job->customReport->name}' successfully dispatched to '{$job->email_recipient}'");

                    $job->update([
                        'last_sent_at' => $now,
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to generate and send scheduled report ID {$job->id}: " . $e->getMessage());
                }
            }
        }
    }
}
