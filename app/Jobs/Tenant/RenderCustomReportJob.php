<?php

namespace App\Jobs\Tenant;

use App\Models\Master\Tenant;
use App\Models\Tenant\CustomReport;
use App\Services\Tenant\ReportBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class RenderCustomReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Tenant $tenant,
        protected CustomReport $report,
        protected array $filters = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ReportBuilderService $reportBuilderService): void
    {
        // 1. Initialize Tenancy Context
        tenancy()->initialize($this->tenant);

        // 2. Export to CSV
        $csv = $reportBuilderService->exportToCsv($this->report, $this->filters);

        // 3. Save file to disk
        $filename = "reports/report_" . $this->report->id . "_" . time() . ".csv";
        Storage::disk('local')->put($filename, $csv);

        // 4. Cache metadata
        Cache::put("report_{$this->report->id}_last_rendered", [
            'status' => 'completed',
            'path' => $filename,
            'rendered_at' => now()->toIso8601String(),
        ], now()->addDays(7));
    }
}
