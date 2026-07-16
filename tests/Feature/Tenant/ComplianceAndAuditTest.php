<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\AuditLog;
use App\Services\Tenant\ComplianceService;
use Carbon\Carbon;

describe('Compliance and Audit Feature Tests', function () {

    beforeEach(function () {
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00'));

        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'compliance-tenant',
        ]);

        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        
        $this->customer = Customer::create([
            'name' => 'John LGPD Customer',
            'email' => 'john@lgpd.com',
            'phone' => '11999999999',
            'cpf_cnpj' => '12345678901',
            'status' => Customer::STATUS_ACTIVE,
        ]);

        $this->driver = Driver::create([
            'name' => 'Speedy Driver LGPD',
            'email' => 'speedy@lgpd.com',
            'phone' => '11988888888',
            'cpf' => '98765432100',
            'cnh' => '12345678901',
            'cnh_expiration' => '2028-12-31',
            'status' => Driver::STATUS_ACTIVE,
        ]);

        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);

        $this->order = OrderOfService::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => OrderOfService::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $this->invoice = Invoice::create([
            'order_of_service_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => 'INV-2026-001',
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal_amount' => 1000.00,
            'tax_amount' => 50.00,
            'total_amount' => 1050.00,
            'paid_amount' => 0.00,
            'created_by' => $this->user->id,
        ]);
    });

    afterEach(function () {
        Carbon::setTestNow(null);
    });

    test('automatic financial audit trailing works on Invoice modifications', function () {
        // Authenticate user to capture user_id in AuditLog
        $this->actingAs($this->user);

        tenancy()->initialize($this->tenant);

        // Modify invoice status
        $this->invoice->update([
            'status' => Invoice::STATUS_SENT,
        ]);

        // Assert audit log exists in tenant database
        $log = AuditLog::where('auditable_type', Invoice::class)
            ->where('auditable_id', $this->invoice->id)
            ->where('action', 'update')
            ->first();

        expect($log)->not->toBeNull();
        expect($log->action)->toBe('update');
        expect($log->old_values['status'])->toBe('draft');
        expect($log->new_values['status'])->toBe('sent');
    });

    test('can anonymize customer and driver personal data under LGPD', function () {
        // 1. Anonymize Customer via API
        $responseCustomer = $this->actingAs($this->user)
            ->postJson("http://compliance-tenant.prime-erp.local/api/compliance/customers/{$this->customer->id}/anonymize");

        $responseCustomer->assertStatus(200);

        tenancy()->initialize($this->tenant);
        $this->customer->refresh();
        expect($this->customer->name)->toContain('Anônimo (LGPD)');
        expect($this->customer->email)->toContain('@lgpd.local');
        expect($this->customer->phone)->toBeNull();
        expect($this->customer->cpf_cnpj)->toBeNull();

        // Assert customer audit trail exists
        $customerLog = AuditLog::where('auditable_type', Customer::class)
            ->where('auditable_id', $this->customer->id)
            ->where('action', 'anonymize')
            ->first();
        expect($customerLog)->not->toBeNull();
        expect($customerLog->old_values['name'])->toBe('John LGPD Customer');

        // 2. Anonymize Driver via API
        $responseDriver = $this->actingAs($this->user)
            ->postJson("http://compliance-tenant.prime-erp.local/api/compliance/drivers/{$this->driver->id}/anonymize");

        $responseDriver->assertStatus(200);

        tenancy()->initialize($this->tenant);
        $this->driver->refresh();
        expect($this->driver->name)->toContain('Anônimo (LGPD)');
        expect($this->driver->email)->toContain('@lgpd.local');
        expect($this->driver->phone)->toBeNull();
        expect($this->driver->cpf)->toBeNull();
        expect($this->driver->cnh)->toBeNull();

        // Assert driver audit trail exists
        $driverLog = AuditLog::where('auditable_type', Driver::class)
            ->where('auditable_id', $this->driver->id)
            ->where('action', 'anonymize')
            ->first();
        expect($driverLog)->not->toBeNull();
        expect($driverLog->old_values['name'])->toBe('Speedy Driver LGPD');
    });

    test('can purge expired audit logs based on retention settings', function () {
        tenancy()->initialize($this->tenant);

        // Create log from 1 year ago (expired)
        $expiredLog = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'update',
            'auditable_type' => Customer::class,
            'auditable_id' => $this->customer->id,
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'suspended'],
            'created_at' => now()->subMonths(12),
        ]);

        // Create log from today (retained)
        $recentLog = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'update',
            'auditable_type' => Customer::class,
            'auditable_id' => $this->customer->id,
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'suspended'],
            'created_at' => now(),
        ]);

        // Trigger Purge via API (retaining 6 months)
        $responsePurge = $this->actingAs($this->user)
            ->postJson('http://compliance-tenant.prime-erp.local/api/compliance/purge-logs', [
                'retention_months' => 6,
            ]);

        $responsePurge->assertStatus(200);
        expect($responsePurge->json('deleted_records_count'))->toBe(1);

        tenancy()->initialize($this->tenant);
        expect(AuditLog::find($expiredLog->id))->toBeNull();
        expect(AuditLog::find($recentLog->id))->not->toBeNull();
    });

    test('can transmit paid invoices to government authorities to generate mock NF-e', function () {
        tenancy()->initialize($this->tenant);

        // Attempting to transmit draft invoice throws 400
        $responseErr = $this->actingAs($this->user)
            ->postJson("http://compliance-tenant.prime-erp.local/api/compliance/invoices/{$this->invoice->id}/nfe");

        $responseErr->assertStatus(400);

        // Mark invoice as paid
        $this->invoice->update(['status' => Invoice::STATUS_PAID]);

        // Transmit NF-e
        $responseOk = $this->actingAs($this->user)
            ->postJson("http://compliance-tenant.prime-erp.local/api/compliance/invoices/{$this->invoice->id}/nfe");

        $responseOk->assertStatus(200);
        expect($responseOk->json('nfe_details.status'))->toBe('success');
        expect($responseOk->json('nfe_details.nfe_number'))->toContain('NFE-');
        expect($responseOk->json('nfe_details.xml_payload'))->toContain('<nfe>');

        // Verify metadata update in DB
        tenancy()->initialize($this->tenant);
        $this->invoice->refresh();
        expect($this->invoice->metadata['nfe']['status'])->toBe('transmitted');
        expect($this->invoice->metadata['nfe']['verification_code'])->not->toBeNull();
    });
});
