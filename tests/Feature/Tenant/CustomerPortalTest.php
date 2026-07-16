<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderMessage;
use App\Models\Tenant\CustomerLoginToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Customer Portal Feature Tests', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'portal-tenant',
        ]);

        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        
        $this->customer = Customer::create([
            'name' => 'John Doe Customer',
            'email' => 'john@customer.com',
            'status' => Customer::STATUS_ACTIVE,
        ]);

        $this->otherCustomer = Customer::create([
            'name' => 'Jane Other Customer',
            'email' => 'jane@customer.com',
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

    test('can generate magic link and authenticate via API', function () {
        // Request login magic link
        $response = $this->postJson('http://portal-tenant.prime-erp.local/api/customer/login', [
            'email' => 'john@customer.com',
        ]);

        $response->assertStatus(200);
        $token = $response->json('token');
        expect($token)->not->toBeEmpty();

        // Authenticate using token
        $responseAuth = $this->postJson('http://portal-tenant.prime-erp.local/api/customer/authenticate', [
            'token' => $token,
        ]);

        $responseAuth->assertStatus(200);
        $accessToken = $responseAuth->json('access_token');
        expect($accessToken)->not->toBeEmpty();

        // Check profile endpoint using Sanctum token
        $responseProfile = $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->getJson('http://portal-tenant.prime-erp.local/api/customer/profile');

        $responseProfile->assertStatus(200);
        expect($responseProfile->json('email'))->toBe('john@customer.com');
    });

    test('rejects expired or used magic link tokens', function () {
        tenancy()->initialize($this->tenant);
        
        // Expired token
        $expiredToken = CustomerLoginToken::create([
            'customer_id' => $this->customer->id,
            'token' => 'expired_token_123',
            'expires_at' => now()->subMinutes(5),
        ]);

        $responseExpired = $this->postJson('http://portal-tenant.prime-erp.local/api/customer/authenticate', [
            'token' => 'expired_token_123',
        ]);
        $responseExpired->assertStatus(401);

        // Already used token
        $usedToken = CustomerLoginToken::create([
            'customer_id' => $this->customer->id,
            'token' => 'used_token_123',
            'expires_at' => now()->addMinutes(30),
            'used_at' => now(),
        ]);

        $responseUsed = $this->postJson('http://portal-tenant.prime-erp.local/api/customer/authenticate', [
            'token' => 'used_token_123',
        ]);
        $responseUsed->assertStatus(401);
    });

    test('customer can view own orders and is blocked from other customers orders', function () {
        // Can list own orders
        $responseList = $this->actingAs($this->customer, 'sanctum')
            ->getJson('http://portal-tenant.prime-erp.local/api/customer/orders');

        $responseList->assertStatus(200);
        $responseList->assertJsonCount(1, 'data');

        // Can view details of own order
        $responseShow = $this->actingAs($this->customer, 'sanctum')
            ->getJson("http://portal-tenant.prime-erp.local/api/customer/orders/{$this->order->id}");
        $responseShow->assertStatus(200);
        expect($responseShow->json('id'))->toBe($this->order->id);

        // Cannot view Jane's order (create order for Jane)
        tenancy()->initialize($this->tenant);
        $janesOrder = OrderOfService::factory()->create([
            'customer_id' => $this->otherCustomer->id,
            'branch_id' => $this->branch->id,
            'vehicle_id' => $this->vehicle->id,
            'created_by' => $this->user->id,
        ]);

        $responseShowJane = $this->actingAs($this->customer, 'sanctum')
            ->getJson("http://portal-tenant.prime-erp.local/api/customer/orders/{$janesOrder->id}");
        $responseShowJane->assertStatus(403);
    });

    test('customer and staff can exchange messages on Order of Service', function () {
        // Customer sends message
        $responseCustomerSend = $this->actingAs($this->customer, 'sanctum')
            ->postJson("http://portal-tenant.prime-erp.local/api/customer/orders/{$this->order->id}/messages", [
                'message' => 'Olá oficina! Quando meu caminhão estará pronto?'
            ]);

        $responseCustomerSend->assertStatus(201);
        expect($responseCustomerSend->json('sender_type'))->toBe('customer');
        expect($responseCustomerSend->json('sender_id'))->toBe((string) $this->customer->id);

        // 2. Staff responds to message
        $responseStaffSend = $this->actingAs($this->user)
            ->postJson("http://portal-tenant.prime-erp.local/api/orders/{$this->order->id}/messages", [
                'message' => 'Olá John! Estamos finalizando a revisão básica, previsão de entrega para amanhã.'
            ]);

        $responseStaffSend->assertStatus(201);
        expect($responseStaffSend->json('sender_type'))->toBe('user');
        expect($responseStaffSend->json('sender_id'))->toBe((string) $this->user->id);

        // 3. Customer lists messages on OS
        $responseMessages = $this->actingAs($this->customer, 'sanctum')
            ->getJson("http://portal-tenant.prime-erp.local/api/customer/orders/{$this->order->id}/messages");

        $responseMessages->assertStatus(200);
        $responseMessages->assertJsonCount(2);
        expect($responseMessages->json('0.message'))->toBe('Olá oficina! Quando meu caminhão estará pronto?');
        expect($responseMessages->json('1.message'))->toBe('Olá John! Estamos finalizando a revisão básica, previsão de entrega para amanhã.');
    });
});
