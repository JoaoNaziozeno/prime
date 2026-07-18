<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

describe('Security Hardening Feature Tests', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'secure-tenant',
        ]);

        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        
        $this->customer = Customer::create([
            'name' => 'Secure John Doe',
            'email' => 'secure_john@customer.com',
            'status' => Customer::STATUS_ACTIVE,
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
            'invoice_number' => 'SEC-001',
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal_amount' => 1000.00,
            'tax_amount' => 50.00,
            'total_amount' => 1050.00,
            'paid_amount' => 0.00,
            'created_by' => $this->user->id,
        ]);

        Cache::clear();
    });

    test('OWASP security headers are present in all tenant API responses', function () {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('http://secure-tenant.prime-erp.local/api/settings');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'no-referrer-when-downgrade');
    });

    test('customer login API endpoint has strict rate limiting (throttle:5,1,login)', function () {
        // Trigger customer login request 6 times
        for ($i = 1; $i <= 6; $i++) {
            $response = $this->postJson('http://secure-tenant.prime-erp.local/api/customer/login', [
                'email' => 'some@customer.com',
            ]);

            dump($i, $response->status(), $response->headers->get('X-RateLimit-Remaining'));

            if ($i === 6) {
                // The 6th request must trigger rate limit blocker
                $response->assertStatus(429);
            } else {
                // Should return validation/authentication failure or similar, not 429
                expect($response->status())->not->toBe(429);
            }
        }
    });

    test('sensitive data in Customer and Invoice notes columns is encrypted in database', function () {
        tenancy()->initialize($this->tenant);

        // 1. Customer notes encryption
        $this->customer->update([
            'notes' => 'Confidential health condition notes for customer.',
        ]);

        // Refresh Eloquent model reads clean text
        $this->customer->refresh();
        expect($this->customer->notes)->toBe('Confidential health condition notes for customer.');

        // Query raw database directly via PDO
        $rawCustomer = DB::connection('tenant')->select('select notes from customers where id = ?', [$this->customer->id])[0];
        expect($rawCustomer->notes)->not->toBeNull();
        expect($rawCustomer->notes)->not->toBe('Confidential health condition notes for customer.');

        // 2. Invoice notes encryption
        $this->invoice->update([
            'notes' => 'Sensitive payment agreements and private discounts.',
        ]);

        // Refresh Eloquent model reads clean text
        $this->invoice->refresh();
        expect($this->invoice->notes)->toBe('Sensitive payment agreements and private discounts.');

        // Query raw database directly via PDO
        $rawInvoice = DB::connection('tenant')->select('select notes from invoices where id = ?', [$this->invoice->id])[0];
        expect($rawInvoice->notes)->not->toBeNull();
        expect($rawInvoice->notes)->not->toBe('Sensitive payment agreements and private discounts.');
    });

    test('security audit console command runs and reports application health status', function () {
        $exitCode = Artisan::call('prime:security-audit');
        $output = Artisan::output();

        expect($exitCode)->toBe(0); // Completed without hard failures
        expect($output)->toContain('ERP SaaS - Relatório de Auditoria');
        expect($output)->toContain('Auditoria concluída');
    });
});
