<?php

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderProductLine;
use App\Models\Tenant\OrderServiceLine;
use App\Models\Tenant\Product;
use App\Models\Tenant\Service;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\Driver;
use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Services\Tenant\ReportingService;
use Livewire\Livewire;
use App\Http\Livewire\Tenant\Dashboard;

describe('Reporting Service Analytics', function () {

    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'reporting-logic-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        $this->reportingService = app(ReportingService::class);
        $this->userId = fake()->uuid();
    });

    test('calculates correct order stats including average turnaround hours', function () {
        // Create completed order that took 10 hours
        $order1 = OrderOfService::factory()->completed()->create([
            'created_at' => now()->subHours(10),
            'actual_end_date' => now(),
        ]);

        // Create another completed order that took 20 hours
        $order2 = OrderOfService::factory()->completed()->create([
            'created_at' => now()->subHours(20),
            'actual_end_date' => now(),
        ]);

        // Create a draft order
        $order3 = OrderOfService::factory()->draft()->create();

        $stats = $this->reportingService->getOrderStats(
            now()->subDays(2)->toDateString(),
            now()->addDays(2)->toDateString()
        );

        expect($stats['total_orders'])->toBe(3);
        expect($stats['completed_count'])->toBe(2);
        // Average should be (10 + 20) / 2 = 15
        expect($stats['average_turnaround_hours'])->toEqual(15.0);
    });

    test('revenue reports fetch correct aggregated amounts and margins', function () {
        $order = OrderOfService::factory()->completed()->create();
        $product = Product::factory()->create(['unit_price' => 200, 'cost_price' => 100]);

        $line = OrderProductLine::factory()->forOrder($order)->forProduct($product)->create([
            'quantity' => 1,
            'unit_price' => 200.00,
            'total_price' => 200.00,
        ]);

        // Create an invoice with subtotal 200, tax 10, total 210, cost 100
        $invoice = Invoice::create([
            'order_of_service_id' => $order->id,
            'customer_id' => $order->customer_id,
            'branch_id' => $order->branch_id,
            'invoice_number' => 'INV-TEST-REP-1',
            'status' => Invoice::STATUS_SENT,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal_amount' => 200.00,
            'discount_amount' => 0.00,
            'tax_amount' => 10.00,
            'total_amount' => 210.00,
            'paid_amount' => 110.00,
            'created_by' => $this->userId,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Peça test',
            'quantity' => 1,
            'unit_price' => 200.00,
            'total_price' => 200.00,
            'itemable_type' => OrderProductLine::class,
            'itemable_id' => $line->id,
        ]);

        $report = $this->reportingService->getRevenueReport(
            now()->subDays(2)->toDateString(),
            now()->addDays(2)->toDateString()
        );

        expect($report['total_billed'])->toEqual(210.00);
        expect($report['total_paid'])->toEqual(110.00);
        expect($report['total_pending'])->toEqual(100.00);
        expect($report['total_cost'])->toEqual(100.00);
        expect($report['net_profit'])->toEqual(110.00); // 210 - 100
    });

    test('driver performance reports expire CNH warnings accurately', function () {
        // Driver with valid CNH
        Driver::factory()->create([
            'name' => 'Valid Driver',
            'cnh_expiration' => now()->addDays(40),
            'status' => 'active',
        ]);

        // Driver with expiring CNH (within 30 days)
        Driver::factory()->create([
            'name' => 'Expiring Driver',
            'cnh_expiration' => now()->addDays(10),
            'status' => 'active',
        ]);

        // Driver with expired CNH
        Driver::factory()->create([
            'name' => 'Expired Driver',
            'cnh_expiration' => now()->subDays(5),
            'status' => 'active',
        ]);

        $report = $this->reportingService->getDriverPerformance();

        expect($report['cnh_status']['valid_count'])->toBe(1);
        expect($report['cnh_status']['expiring_count'])->toBe(1);
        expect($report['cnh_status']['expired_count'])->toBe(1);
        expect($report['expired_alerts'][0]['name'])->toBe('Expired Driver');
        expect($report['expiring_alerts'][0]['name'])->toBe('Expiring Driver');
    });

});

describe('Report API Endpoints & CSV Exports', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->regularUser = User::factory()->create(['role' => 'user']); // not admin/superadmin
        
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'reporting-api-tenant',
        ]);
        
        tenancy()->initialize($this->tenant);
        
        // Populate products for inventory report
        Product::factory()->create([
            'sku' => 'PROD-REP-1',
            'name' => 'Caneca Especial',
            'stock_quantity' => 10,
            'cost_price' => 5.00,
            'unit_price' => 15.00,
        ]);
    });

    test('regular user is blocked from viewing reports by ReportPolicy', function () {
        $url = "http://reporting-api-tenant.prime-erp.local/api/reports/inventory";

        $response = $this->actingAs($this->regularUser)->getJson($url);
        $response->assertStatus(403);
    });

    test('admin user can view inventory statistics', function () {
        $url = "http://reporting-api-tenant.prime-erp.local/api/reports/inventory";

        $response = $this->actingAs($this->user)->getJson($url);
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'total_unique_products' => 1,
            'total_stock_items' => 10,
            'valuation_cost' => 50,
            'valuation_retail' => 150,
        ]);
    });

    test('admin user can download CSV report', function () {
        $url = "http://reporting-api-tenant.prime-erp.local/api/reports/inventory?format=csv";

        $response = $this->actingAs($this->user)->get($url);
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $response->streamedContent();
        expect($content)->toContain('PROD-REP-1');
        expect($content)->toContain('Caneca Especial');
    });

    test('Livewire dashboard component loads correct values', function () {
        Livewire::test(Dashboard::class)
            ->assertSet('startDate', now()->subDays(30)->toDateString())
            ->assertSet('endDate', now()->toDateString())
            ->assertSee('Painel Analítico de Controle');
    });

});
