<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\CustomReport;
use App\Models\Tenant\ScheduledReport;
use App\Models\Tenant\DailyMetricsSnapshot;
use App\Models\Tenant\Invoice;
use App\Services\Tenant\ReportBuilderService;
use Carbon\Carbon;

describe('Advanced Reports and BI Feature Tests', function () {

    beforeEach(function () {
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00'));

        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'reports-tenant',
        ]);

        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        
        $this->customer = Customer::create([
            'name' => 'Reports John Doe Customer',
            'email' => 'reports_john@customer.com',
            'status' => Customer::STATUS_ACTIVE,
        ]);

        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);

        // Create completed order with actual cost
        $this->completedOrder = OrderOfService::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => OrderOfService::STATUS_COMPLETED,
            'actual_cost' => 1500.00,
            'actual_end_date' => now(),
            'created_by' => $this->user->id,
        ]);

        // Create paid invoice for this completed order
        $this->invoice = Invoice::create([
            'order_of_service_id' => $this->completedOrder->id,
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-001',
            'status' => Invoice::STATUS_PAID,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal_amount' => 2000.00,
            'tax_amount' => 100.00,
            'total_amount' => 2100.00,
            'paid_amount' => 2100.00,
            'created_by' => $this->user->id,
        ]);
    });

    afterEach(function () {
        Carbon::setTestNow(null);
    });

    test('can CRUD custom reports and execute queries and export CSV via API', function () {
        // 1. Create a custom report definition
        $response = $this->actingAs($this->user)
            ->postJson('http://reports-tenant.prime-erp.local/api/custom-reports', [
                'name' => 'Relatório de OS Completas',
                'description' => 'Listagem de ordens concluídas',
                'model_type' => CustomReport::TYPE_ORDERS,
                'columns' => ['id', 'status', 'actual_cost'],
                'filters' => ['status' => 'completed'],
                'group_by' => null,
            ]);

        $response->assertStatus(201);
        $reportId = $response->json('id');
        expect($response->json('name'))->toBe('Relatório de OS Completas');

        // 2. Execute report query
        $responseExecute = $this->actingAs($this->user)
            ->getJson("http://reports-tenant.prime-erp.local/api/custom-reports/{$reportId}/execute");

        $responseExecute->assertStatus(200);
        expect($responseExecute->json('data'))->toHaveCount(1);
        expect($responseExecute->json('data.0.status'))->toBe('completed');
        expect((float)$responseExecute->json('data.0.actual_cost'))->toBe(1500.00);

        // 3. Export report to CSV
        $responseExport = $this->actingAs($this->user)
            ->getJson("http://reports-tenant.prime-erp.local/api/custom-reports/{$reportId}/export");

        $responseExport->assertStatus(200);
        $responseExport->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        expect($responseExport->getContent())->toContain('id,status,actual_cost');
        expect($responseExport->getContent())->toContain('completed,1500');
    });

    test('can generate daily metrics snapshot and retrieve BI data', function () {
        // 1. Trigger snapshot calculation via API
        $responseTrigger = $this->actingAs($this->user)
            ->postJson('http://reports-tenant.prime-erp.local/api/bi/snapshots/trigger', [
                'date' => '2026-07-16',
            ]);

        $responseTrigger->assertStatus(200);
        expect((float)$responseTrigger->json('snapshot.revenue'))->toBe(2100.00); // 2100.00 from invoice
        expect((float)$responseTrigger->json('snapshot.cost'))->toBe(1500.00);    // 1500.00 from order
        expect((float)$responseTrigger->json('snapshot.margin'))->toBe(600.00);   // 2100 - 1500

        // 2. Retrieve BI Dashboard range data
        $responseDashboard = $this->actingAs($this->user)
            ->getJson('http://reports-tenant.prime-erp.local/api/bi/dashboard?start_date=2026-07-10&end_date=2026-07-20');

        $responseDashboard->assertStatus(200);
        expect($responseDashboard->json('labels'))->toHaveCount(1);
        expect($responseDashboard->json('labels.0'))->toBe('2026-07-16');
        expect((float)$responseDashboard->json('series.revenue.0'))->toBe(2100.0);
        expect((float)$responseDashboard->json('series.cost.0'))->toBe(1500.0);
    });

    test('can process scheduled reports automatically', function () {
        tenancy()->initialize($this->tenant);

        // Create Custom Report
        $report = CustomReport::create([
            'name' => 'Relatório Periódico',
            'model_type' => CustomReport::TYPE_ORDERS,
            'columns' => ['id', 'status'],
            'filters' => [],
            'created_by' => $this->user->id,
        ]);

        // Create Scheduled Job
        $job = ScheduledReport::create([
            'custom_report_id' => $report->id,
            'user_id' => $this->user->id,
            'frequency' => ScheduledReport::FREQUENCY_DAILY,
            'email_recipient' => 'boss@company.com',
            'is_active' => true,
        ]);

        // Execute scheduled job runner
        $service = app(ReportBuilderService::class);
        $service->processScheduledReports();

        // Verify scheduled job updated its last_sent_at timestamp
        tenancy()->initialize($this->tenant);
        $job->refresh();
        expect($job->last_sent_at)->not->toBeNull();
        expect($job->last_sent_at->toDateString())->toBe('2026-07-16');
    });
});
