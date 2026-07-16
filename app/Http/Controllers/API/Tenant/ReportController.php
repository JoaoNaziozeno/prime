<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\ReportingService;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Product;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Report;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportingService $reportingService
    ) {}

    /**
     * Helper to return CSV download stream
     */
    private function exportToCsv(array $headers, array $rows, string $filename): StreamedResponse
    {
        $callback = function() use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($file, $row, ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$filename}_" . date('Ymd_His') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }

    /**
     * GET /api/reports/orders
     */
    public function orders(Request $request)
    {
        $this->authorize('view', Report::class);

        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $stats = $this->reportingService->getOrderStats($startDate, $endDate);

        if ($request->get('format') === 'csv') {
            $this->authorize('export', Report::class);

            $orders = OrderOfService::whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ])->with(['customer', 'vehicle'])->get();

            $headers = ['ID', 'OS_Ref', 'Cliente', 'Veiculo', 'Status', 'Prioridade', 'Custo_Estimado', 'Data_Criacao'];
            $rows = [];

            foreach ($orders as $order) {
                $rows[] = [
                    $order->id,
                    $order->reference_number,
                    $order->customer->name ?? 'N/A',
                    $order->vehicle->plate ?? 'N/A',
                    $order->status,
                    $order->priority,
                    $order->estimated_cost,
                    $order->created_at->toDateString(),
                ];
            }

            return $this->exportToCsv($headers, $rows, 'relatorio_ordens_servico');
        }

        return response()->json($stats);
    }

    /**
     * GET /api/reports/revenue
     */
    public function revenue(Request $request)
    {
        $this->authorize('view', Report::class); // using OS or custom permission

        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $stats = $this->reportingService->getRevenueReport($startDate, $endDate);

        if ($request->get('format') === 'csv') {
            $this->authorize('export', Report::class);

            $headers = ['Data', 'Total_Faturado_R$'];
            $rows = [];

            foreach ($stats['daily_revenue_series'] as $date => $amount) {
                $rows[] = [$date, $amount];
            }

            return $this->exportToCsv($headers, $rows, 'relatorio_faturamento_diario');
        }

        return response()->json($stats);
    }

    /**
     * GET /api/reports/inventory
     */
    public function inventory(Request $request)
    {
        $this->authorize('view', Report::class);

        $stats = $this->reportingService->getInventoryReport();

        if ($request->get('format') === 'csv') {
            $this->authorize('export', Report::class);

            $products = Product::with('warehouseLocation')->get();

            $headers = ['SKU', 'Produto', 'Localizacao', 'Estoque_Atual', 'Estoque_Minimo', 'Preco_Custo_R$', 'Preco_Venda_R$', 'Valor_Total_Estoque_R$'];
            $rows = [];

            foreach ($products as $p) {
                $rows[] = [
                    $p->sku,
                    $p->name,
                    $p->warehouseLocation->code ?? 'N/A',
                    $p->stock_quantity,
                    $p->min_stock_level,
                    $p->cost_price,
                    $p->unit_price,
                    (float) $p->stock_quantity * (float) $p->cost_price,
                ];
            }

            return $this->exportToCsv($headers, $rows, 'relatorio_estoque');
        }

        return response()->json($stats);
    }

    /**
     * GET /api/reports/customers
     */
    public function customers(Request $request)
    {
        $this->authorize('view', Report::class);

        $stats = $this->reportingService->getCustomerReport();

        if ($request->get('format') === 'csv') {
            $this->authorize('export', Report::class);

            $headers = ['Cliente', 'Total_Faturado_R$', 'Qtd_Faturas'];
            $rows = [];

            foreach ($stats['top_ltv_customers'] as $c) {
                $rows[] = [
                    $c['customer_name'],
                    $c['total_billed'],
                    $c['invoice_count'],
                ];
            }

            return $this->exportToCsv($headers, $rows, 'relatorio_clientes_ltv');
        }

        return response()->json($stats);
    }

    /**
     * GET /api/reports/drivers
     */
    public function drivers(Request $request)
    {
        $this->authorize('view', Report::class);

        $stats = $this->reportingService->getDriverPerformance();

        if ($request->get('format') === 'csv') {
            $this->authorize('export', Report::class);

            $drivers = Driver::all();

            $headers = ['Motorista', 'CPF', 'CNH', 'Categoria', 'Vencimento_CNH', 'Dias_Ate_Vencer', 'Status'];
            $rows = [];

            foreach ($drivers as $d) {
                $rows[] = [
                    $d->name,
                    $d->cpf,
                    $d->cnh,
                    $d->cnh_category,
                    $d->cnh_expiration ? $d->cnh_expiration->toDateString() : 'N/A',
                    $d->getDaysUntilCnhExpiration() ?? 'N/A',
                    $d->status_name,
                ];
            }

            return $this->exportToCsv($headers, $rows, 'relatorio_motoristas_performance');
        }

        return response()->json($stats);
    }
}
