<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\OrderOfService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Mobile App Backend Feature Tests', function () {

    beforeEach(function () {
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00'));

        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'mobile-tenant',
        ]);

        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        
        $this->customer = Customer::create([
            'name' => 'John Mobile Customer',
            'email' => 'john@mobile.com',
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
    });

    afterEach(function () {
        Carbon::setTestNow(null);
    });

    test('can pull delta changes using last_sync_at', function () {
        // First sync pull (initial - returns everything)
        $response1 = $this->actingAs($this->user)
            ->getJson('http://mobile-tenant.prime-erp.local/api/mobile/sync');

        $response1->assertStatus(200);
        $syncTimestamp = $response1->json('sync_timestamp');
        expect($response1->json('changes.customers'))->toHaveCount(1);
        expect($response1->json('changes.orders'))->toHaveCount(1);

        // Advance time and create new resources
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:05:00'));

        tenancy()->initialize($this->tenant);
        $newCustomer = Customer::create([
            'name' => 'New Customer Sync',
            'email' => 'new@sync.com',
            'status' => Customer::STATUS_ACTIVE,
        ]);

        // Second sync pull (delta - returns only new customer)
        $response2 = $this->actingAs($this->user)
            ->getJson("http://mobile-tenant.prime-erp.local/api/mobile/sync?last_sync_at=" . urlencode($syncTimestamp));

        $response2->assertStatus(200);
        expect($response2->json('changes.customers'))->toHaveCount(1);
        expect($response2->json('changes.customers.0.name'))->toBe('New Customer Sync');
        expect($response2->json('changes.orders'))->toBeEmpty();
    });

    test('can push changes successfully and resolve conflicts using last-write-wins', function () {
        tenancy()->initialize($this->tenant);

        // Scenario 1: Push updates without conflict (Client and Server are aligned)
        $response1 = $this->actingAs($this->user)
            ->postJson('http://mobile-tenant.prime-erp.local/api/mobile/sync', [
                'changes' => [
                    [
                        'model' => 'orders',
                        'id' => $this->order->id,
                        'data' => [
                            'description' => 'Updated description offline',
                        ],
                        'updated_at' => '2026-07-16 12:01:00',
                    ]
                ]
            ]);

        $response1->assertStatus(200);
        expect($response1->json('success'))->toHaveCount(1);
        expect($response1->json('conflicts'))->toBeEmpty();

        tenancy()->initialize($this->tenant);
        $this->order->refresh();
        expect($this->order->description)->toBe('Updated description offline');

        // Scenario 2: Conflict detected (Server is newer than client request)
        // Server advances to 12:10:00 and gets updated status
        Carbon::setTestNow(Carbon::parse('2026-07-16 12:10:00'));
        $this->order->update(['status' => OrderOfService::STATUS_APPROVED]);

        // Client pushes change made at 12:05:00 (older than server's 12:10:00)
        $responseConflict = $this->actingAs($this->user)
            ->postJson('http://mobile-tenant.prime-erp.local/api/mobile/sync', [
                'changes' => [
                    [
                        'model' => 'orders',
                        'id' => $this->order->id,
                        'data' => [
                            'status' => OrderOfService::STATUS_IN_PROGRESS,
                        ],
                        'updated_at' => '2026-07-16 12:05:00',
                    ]
                ]
            ]);

        $responseConflict->assertStatus(200);
        expect($responseConflict->json('success'))->toBeEmpty();
        expect($responseConflict->json('conflicts'))->toHaveCount(1);
        expect($responseConflict->json('conflicts.0.id'))->toBe($this->order->id);
        expect($responseConflict->json('conflicts.0.server_data.status'))->toBe(OrderOfService::STATUS_APPROVED);

        tenancy()->initialize($this->tenant);
        $this->order->refresh();
        expect($this->order->status)->toBe(OrderOfService::STATUS_APPROVED); // Server won!
    });

    test('can retrieve lightweight response formats for mobile app listing', function () {
        $response = $this->actingAs($this->user)
            ->getJson('http://mobile-tenant.prime-erp.local/api/mobile/orders');

        $response->assertStatus(200);
        $data = $response->json('data.0');

        expect($data)->toHaveKey('id');
        expect($data)->toHaveKey('status');
        expect($data)->toHaveKey('priority');
        expect($data)->toHaveKey('reference_number');

        // Verify heavy relations/fields like description are not returned in the root object
        expect($data)->not->toHaveKey('description');
        expect($data)->not->toHaveKey('internal_notes');
    });
});
