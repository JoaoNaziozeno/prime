<?php

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderProductLine;
use App\Models\Tenant\OrderServiceLine;
use App\Models\Tenant\Product;
use App\Models\Tenant\Service;
use App\Models\Tenant\WarehouseLocation;
use App\Models\Tenant\InventoryLog;
use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Services\Tenant\CostAllocationService;
use App\DTOs\Tenant\CreateOrderProductLineDTO;
use App\DTOs\Tenant\UpdateOrderProductLineDTO;
use App\DTOs\Tenant\CreateOrderServiceLineDTO;
use App\DTOs\Tenant\UpdateOrderServiceLineDTO;

describe('CostAllocation Service Logic', function () {

    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'cost-alloc-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        $this->service = app(CostAllocationService::class);
        $this->userId = fake()->uuid();
    });

    test('adding product line calculates total price and cost and recalculates order estimated cost', function () {
        $order = OrderOfService::factory()->draft()->create(['estimated_cost' => 0]);
        $product = Product::factory()->create([
            'unit_price' => 100.00,
            'cost_price' => 60.00,
            'stock_quantity' => 10,
        ]);

        $dto = new CreateOrderProductLineDTO(
            product_id: $product->id,
            quantity: 2.0,
            unit_price: null,
            cost_price: null
        );

        $line = $this->service->addProductLine($order, $dto, $this->userId);

        expect($line->total_price)->toEqual(200.00);
        expect($line->total_cost)->toEqual(120.00);
        expect($line->unit_price)->toEqual(100.00);
        expect($line->cost_price)->toEqual(60.00);

        expect($order->refresh()->estimated_cost)->toEqual(200.00);
    });

    test('adding service line calculates total price and cost and recalculates order estimated cost', function () {
        $order = OrderOfService::factory()->draft()->create(['estimated_cost' => 0]);
        $service = Service::factory()->create([
            'base_price' => 150.00,
        ]);

        $dto = new CreateOrderServiceLineDTO(
            service_id: $service->id,
            quantity: 3.0,
            unit_price: null,
            cost_price: 50.00
        );

        $line = $this->service->addServiceLine($order, $dto, $this->userId);

        expect($line->total_price)->toEqual(450.00);
        expect($line->total_cost)->toEqual(150.00);
        expect($line->unit_price)->toEqual(150.00);
        expect($line->cost_price)->toEqual(50.00);

        expect($order->refresh()->estimated_cost)->toEqual(450.00);
    });

    test('order calculated properties compute margins correctly', function () {
        $order = OrderOfService::factory()->draft()->create(['estimated_cost' => 0]);
        
        $product = Product::factory()->create(['unit_price' => 100, 'cost_price' => 60]);
        $service = Service::factory()->create(['base_price' => 150]);

        $this->service->addProductLine($order, new CreateOrderProductLineDTO(
            product_id: $product->id,
            quantity: 2.0
        ), $this->userId);

        $this->service->addServiceLine($order, new CreateOrderServiceLineDTO(
            service_id: $service->id,
            quantity: 1.0,
            cost_price: 50.00
        ), $this->userId);

        // Product Line: billed=200, cost=120
        // Service Line: billed=150, cost=50
        // Totals: billed=350, cost=170
        // Margin amount: 350 - 170 = 180
        // Margin percentage: (180 / 350) * 100 = 51.43%

        $order->refresh();
        expect($order->total_billed_price)->toEqual(350.00);
        expect($order->total_cost_price)->toEqual(170.00);
        expect($order->margin_amount)->toEqual(180.00);
        expect($order->margin_percentage)->toEqualWithDelta(51.43, 0.1);
    });

    test('adding product line to in_progress order triggers stock deduction', function () {
        $order = OrderOfService::factory()->inProgress()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);

        $dto = new CreateOrderProductLineDTO(
            product_id: $product->id,
            quantity: 3.0
        );

        $this->service->addProductLine($order, $dto, $this->userId);

        expect($product->refresh()->stock_quantity)->toBe(7);

        // Verify inventory log was created
        $log = InventoryLog::latest()->first();
        expect($log->product_id)->toBe($product->id);
        expect($log->type)->toBe(InventoryLog::TYPE_OUTBOUND);
        expect($log->quantity)->toBe(3);
    });

    test('removing product line from in_progress order refunds stock', function () {
        $order = OrderOfService::factory()->inProgress()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);

        $dto = new CreateOrderProductLineDTO(
            product_id: $product->id,
            quantity: 3.0
        );

        $line = $this->service->addProductLine($order, $dto, $this->userId);
        expect($product->refresh()->stock_quantity)->toBe(7);

        $this->service->removeProductLine($line, $this->userId);
        expect($product->refresh()->stock_quantity)->toBe(10);

        // Verify refund log was created
        $log = InventoryLog::latest()->first();
        expect($log->product_id)->toBe($product->id);
        expect($log->type)->toBe(InventoryLog::TYPE_INBOUND);
        expect($log->quantity)->toBe(3);
    });

    test('updating product line quantity in in_progress order adjusts stock correctly', function () {
        $order = OrderOfService::factory()->inProgress()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);

        $dto = new CreateOrderProductLineDTO(
            product_id: $product->id,
            quantity: 3.0
        );

        $line = $this->service->addProductLine($order, $dto, $this->userId);
        expect($product->refresh()->stock_quantity)->toBe(7);

        // Increase quantity to 5 (deduct 2 more)
        $this->service->updateProductLine($line, new UpdateOrderProductLineDTO(quantity: 5.0), $this->userId);
        expect($product->refresh()->stock_quantity)->toBe(5);

        // Decrease quantity to 2 (refund 3)
        $this->service->updateProductLine($line, new UpdateOrderProductLineDTO(quantity: 2.0), $this->userId);
        expect($product->refresh()->stock_quantity)->toBe(8);
    });
});

describe('CostAllocation Controller API', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'cost-api-tenant',
        ]);
        
        tenancy()->initialize($this->tenant);
        $this->order = OrderOfService::factory()->draft()->create();
        $this->product = Product::factory()->create(['unit_price' => 100, 'cost_price' => 60]);
        $this->service = Service::factory()->create(['base_price' => 150]);
    });

    test('can add and list product lines through API', function () {
        $url = "http://cost-api-tenant.prime-erp.local/api/orders/{$this->order->id}/products";

        $response = $this->actingAs($this->user)
            ->postJson($url, [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => 120.00,
                'cost_price' => 65.00,
                'notes' => 'Some product notes',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'unit_price' => "120.00",
            'total_price' => "240.00",
            'notes' => 'Some product notes',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson($url);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    });

    test('can update and delete product lines through API', function () {
        $line = OrderProductLine::factory()
            ->forOrder($this->order)
            ->forProduct($this->product)
            ->create(['quantity' => 1]);

        $url = "http://cost-api-tenant.prime-erp.local/api/orders/{$this->order->id}/products/{$line->id}";

        $response = $this->actingAs($this->user)
            ->putJson($url, [
                'quantity' => 4,
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'quantity' => "4.00",
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson($url);

        $response->assertStatus(204);
        expect(OrderProductLine::find($line->id))->toBeNull();
    });

    test('can add, update and delete service lines through API', function () {
        $url = "http://cost-api-tenant.prime-erp.local/api/orders/{$this->order->id}/services";

        $response = $this->actingAs($this->user)
            ->postJson($url, [
                'service_id' => $this->service->id,
                'quantity' => 5,
                'unit_price' => 160.00,
                'cost_price' => 40.00,
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'quantity' => "5.00",
            'unit_price' => "160.00",
            'total_price' => "800.00",
        ]);

        $lineId = $response->json('id');
        $lineUrl = "{$url}/{$lineId}";

        // Update
        $response = $this->actingAs($this->user)
            ->putJson($lineUrl, [
                'quantity' => 6,
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'quantity' => "6.00",
            'total_price' => "960.00",
        ]);

        // Start workflow transition
        $response = $this->actingAs($this->user)
            ->postJson("{$lineUrl}/start");
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'in_progress']);

        // Complete workflow transition
        $response = $this->actingAs($this->user)
            ->postJson("{$lineUrl}/complete", ['hours_spent' => 5.5]);
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'completed',
            'hours_spent' => "5.50",
        ]);

        // Delete
        $response = $this->actingAs($this->user)
            ->deleteJson($lineUrl);
        $response->assertStatus(204);
    });
});
