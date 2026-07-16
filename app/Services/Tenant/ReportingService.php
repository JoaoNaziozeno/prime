<?php

namespace App\Services\Tenant;

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Product;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Driver;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportingService
{
    /**
     * Get Order statistics
     */
    public function getOrderStats(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $orders = OrderOfService::whereBetween('created_at', [$start, $end])->get();

        $byStatus = $orders->groupBy('status')->map->count();
        $byPriority = $orders->groupBy('priority')->map->count();

        // Calculate average turnaround time in hours for completed orders
        $completedOrders = OrderOfService::whereBetween('created_at', [$start, $end])
            ->where('status', OrderOfService::STATUS_COMPLETED)
            ->whereNotNull('actual_end_date')
            ->get();

        $totalHours = 0;
        foreach ($completedOrders as $order) {
            $totalHours += $order->created_at->diffInHours($order->actual_end_date);
        }

        $avgTurnaroundHours = $completedOrders->isEmpty() ? 0.0 : round($totalHours / $completedOrders->count(), 1);

        return [
            'total_orders' => $orders->count(),
            'by_status' => $byStatus->toArray(),
            'by_priority' => $byPriority->toArray(),
            'average_turnaround_hours' => $avgTurnaroundHours,
            'completed_count' => $completedOrders->count(),
        ];
    }

    /**
     * Get Revenue statistics
     */
    public function getRevenueReport(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $invoices = Invoice::whereBetween('issue_date', [$start, $end])
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->get();

        $totalBilled = $invoices->sum(fn($i) => (float) $i->total_amount);
        $totalPaid = $invoices->sum(fn($i) => (float) $i->paid_amount);
        $totalPending = max(0.00, $totalBilled - $totalPaid);
        $totalTaxes = $invoices->sum(fn($i) => (float) $i->tax_amount);

        // Calculate costs of items fatured (by pulling cost_price of linked OrderProductLines & OrderServiceLines via invoice items)
        // Let's load invoice items that are polymorphically linked to productLines and serviceLines
        $cost = 0.00;
        foreach ($invoices as $invoice) {
            $invoice->load('items.itemable');
            foreach ($invoice->items as $item) {
                if ($item->itemable) {
                    $cost += (float) ($item->itemable->cost_price * $item->quantity);
                }
            }
        }

        $profit = $totalBilled - $cost;
        $profitMargin = $totalBilled > 0 ? round(($profit / $totalBilled) * 100, 2) : 0.00;

        // Daily series
        $series = $invoices->groupBy(fn($i) => $i->issue_date->toDateString())
            ->map(fn($group) => $group->sum(fn($i) => (float) $i->total_amount))
            ->toArray();

        return [
            'total_billed' => round($totalBilled, 2),
            'total_paid' => round($totalPaid, 2),
            'total_pending' => round($totalPending, 2),
            'total_taxes' => round($totalTaxes, 2),
            'total_cost' => round($cost, 2),
            'net_profit' => round($profit, 2),
            'profit_margin_percentage' => $profitMargin,
            'daily_revenue_series' => $series,
        ];
    }

    /**
     * Get Inventory statistics
     */
    public function getInventoryReport(): array
    {
        $products = Product::with('warehouseLocation')->get();

        $totalItems = $products->sum('stock_quantity');
        $totalCostValuation = $products->sum(fn($p) => (float) $p->stock_quantity * (float) $p->cost_price);
        $totalRetailValuation = $products->sum(fn($p) => (float) $p->stock_quantity * (float) $p->unit_price);
        $projectedMargin = $totalRetailValuation > 0 
            ? round((($totalRetailValuation - $totalCostValuation) / $totalRetailValuation) * 100, 2) 
            : 0.00;

        // Low stock products
        $lowStock = $products->filter(fn($p) => (int) $p->stock_quantity <= (int) $p->min_stock_level)
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'stock' => $p->stock_quantity,
                'min_stock' => $p->min_stock_level,
                'location' => $p->warehouseLocation->code ?? 'N/A',
            ])->values()->toArray();

        // Location distribution
        $byLocation = $products->groupBy(fn($p) => $p->warehouseLocation->code ?? 'Sem Localização')
            ->map(fn($group) => [
                'item_count' => $group->sum('stock_quantity'),
                'valuation_cost' => $group->sum(fn($p) => $p->stock_quantity * $p->cost_price),
            ])->toArray();

        return [
            'total_unique_products' => $products->count(),
            'total_stock_items' => $totalItems,
            'valuation_cost' => round($totalCostValuation, 2),
            'valuation_retail' => round($totalRetailValuation, 2),
            'projected_margin_percentage' => $projectedMargin,
            'low_stock_alerts' => $lowStock,
            'distribution_by_location' => $byLocation,
        ];
    }

    /**
     * Get Customer statistics
     */
    public function getCustomerReport(): array
    {
        $customers = Customer::all();

        // Top customers by LTV (total amount paid or billed)
        $topCustomers = Invoice::where('status', '!=', Invoice::STATUS_CANCELLED)
            ->with('customer')
            ->get()
            ->groupBy('customer_id')
            ->map(fn($group) => [
                'customer_name' => $group->first()->customer->name ?? 'N/A',
                'total_billed' => $group->sum(fn($i) => (float) $i->total_amount),
                'invoice_count' => $group->count(),
            ])
            ->sortByDesc('total_billed')
            ->take(10)
            ->values()
            ->toArray();

        $byStatus = $customers->groupBy('status')->map->count()->toArray();

        return [
            'total_customers' => $customers->count(),
            'by_status' => $byStatus,
            'top_ltv_customers' => $topCustomers,
        ];
    }

    /**
     * Get Driver performance/warnings report
     */
    public function getDriverPerformance(): array
    {
        $drivers = Driver::all();

        $byStatus = $drivers->groupBy('status')->map->count()->toArray();

        // CNH Alert levels
        $expired = [];
        $expiring = [];
        $valid = 0;

        foreach ($drivers as $driver) {
            if ($driver->isCnhExpired()) {
                $expired[] = [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'cnh' => $driver->cnh,
                    'expiration_date' => $driver->cnh_expiration->toDateString(),
                    'days_expired' => abs($driver->getDaysUntilCnhExpiration()),
                ];
            } elseif ($driver->isCnhExpiring()) {
                $expiring[] = [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'cnh' => $driver->cnh,
                    'expiration_date' => $driver->cnh_expiration->toDateString(),
                    'days_remaining' => $driver->getDaysUntilCnhExpiration(),
                ];
            } else {
                $valid++;
            }
        }

        return [
            'total_drivers' => $drivers->count(),
            'by_status' => $byStatus,
            'cnh_status' => [
                'valid_count' => $valid,
                'expired_count' => count($expired),
                'expiring_count' => count($expiring),
            ],
            'expired_alerts' => $expired,
            'expiring_alerts' => $expiring,
        ];
    }
}
