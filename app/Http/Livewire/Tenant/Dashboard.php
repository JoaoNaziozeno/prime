<?php

namespace App\Http\Livewire\Tenant;

use Livewire\Component;
use App\Services\Tenant\ReportingService;

class Dashboard extends Component
{
    public string $startDate = '';
    public string $endDate = '';

    public function mount(): void
    {
        $this->startDate = now()->subDays(30)->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function render(ReportingService $reportingService)
    {
        return view('livewire.tenant.dashboard', [
            'orderStats' => $reportingService->getOrderStats($this->startDate, $this->endDate),
            'revenueReport' => $reportingService->getRevenueReport($this->startDate, $this->endDate),
            'inventoryReport' => $reportingService->getInventoryReport(),
            'customerReport' => $reportingService->getCustomerReport(),
            'driverPerformance' => $reportingService->getDriverPerformance(),
        ]);
    }
}
