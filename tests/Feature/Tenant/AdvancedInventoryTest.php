<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\WarehouseLocation;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderProductLine;
use App\Services\Tenant\CostAllocationService;
use App\DTOs\Tenant\CreateOrderProductLineDTO;
use App\DTOs\Tenant\UpdateOrderProductLineDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Advanced Inventory Feature Tests', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'adv-inv-tenant',
        ]);

        tenancy()->initialize($this->tenant);

        // Seed basic dependencies inside tenant database
        $this->branch = Branch::factory()->create();
        $this->location = WarehouseLocation::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);

        $this->product = Product::factory()->create([
            'warehouse_location_id' => $this->location->id,
            'stock_quantity' => 10,
            'is_serialized' => false,
            'has_batches' => false,
        ]);
    });

    test('can manage supplier CRUD via API', function () {
        // Create supplier
        $response = $this->actingAs($this->user)
            ->postJson('http://adv-inv-tenant.prime-erp.local/api/suppliers', [
                'name' => 'Distribuidora AutoPeças Ltda',
                'cnpj' => '12.345.678/0001-99',
                'email' => 'contato@distribuidora.com',
                'phone' => '(11) 99999-9999',
                'address' => 'Rua das Peças, 123',
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $supplierId = $response->json('id');

        // Read supplier
        $responseRead = $this->actingAs($this->user)
            ->getJson("http://adv-inv-tenant.prime-erp.local/api/suppliers/{$supplierId}");
        $responseRead->assertStatus(200);
        expect($responseRead->json('name'))->toBe('Distribuidora AutoPeças Ltda');

        // Update supplier
        $responseUpdate = $this->actingAs($this->user)
            ->putJson("http://adv-inv-tenant.prime-erp.local/api/suppliers/{$supplierId}", [
                'name' => 'Distribuidora AutoPeças Ltda - Alterada',
                'cnpj' => '12.345.678/0001-99',
                'email' => 'novo_contato@distribuidora.com',
                'is_active' => true,
            ]);
        $responseUpdate->assertStatus(200);
        expect($responseUpdate->json('name'))->toBe('Distribuidora AutoPeças Ltda - Alterada');

        // Delete supplier
        $responseDelete = $this->actingAs($this->user)
            ->deleteJson("http://adv-inv-tenant.prime-erp.local/api/suppliers/{$supplierId}");
        $responseDelete->assertStatus(204);

        tenancy()->initialize($this->tenant);
        expect(Supplier::find($supplierId))->toBeNull();
    });

    test('can execute purchase order lifecycle', function () {
        tenancy()->initialize($this->tenant);
        $supplier = Supplier::create([
            'name' => 'Fornecedor Teste',
            'cnpj' => '99.999.999/0001-99',
        ]);

        // 1. Create Purchase Order (Draft)
        $response = $this->actingAs($this->user)
            ->postJson('http://adv-inv-tenant.prime-erp.local/api/purchase-orders', [
                'supplier_id' => $supplier->id,
                'branch_id' => $this->branch->id,
                'notes' => 'Pedido de teste de estoque',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 5,
                        'unit_cost' => 50.00,
                    ]
                ]
            ]);

        $response->assertStatus(201);
        $poId = $response->json('id');
        expect($response->json('status'))->toBe(PurchaseOrder::STATUS_DRAFT);
        expect((float)$response->json('total_amount'))->toBe(250.00);

        // 2. Approve Purchase Order
        $responseApprove = $this->actingAs($this->user)
            ->postJson("http://adv-inv-tenant.prime-erp.local/api/purchase-orders/{$poId}/approve");
        $responseApprove->assertStatus(200);
        expect($responseApprove->json('purchase_order.status'))->toBe(PurchaseOrder::STATUS_APPROVED);

        // 3. Receive Purchase Order
        tenancy()->initialize($this->tenant);
        $poItem = PurchaseOrderItem::where('purchase_order_id', $poId)->first();

        $responseReceive = $this->actingAs($this->user)
            ->postJson("http://adv-inv-tenant.prime-erp.local/api/purchase-orders/{$poId}/receive", [
                'received_items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received' => 5,
                    ]
                ]
            ]);

        $responseReceive->assertStatus(200);
        expect($responseReceive->json('purchase_order.status'))->toBe(PurchaseOrder::STATUS_RECEIVED);

        // Verify stock has updated
        tenancy()->initialize($this->tenant);
        $this->product->refresh();
        expect($this->product->stock_quantity)->toBe(15); // 10 initial + 5 received
    });

    test('can receive and allocate serialized products', function () {
        tenancy()->initialize($this->tenant);
        $supplier = Supplier::create(['name' => 'F1']);
        $serializedProduct = Product::factory()->create([
            'warehouse_location_id' => $this->location->id,
            'is_serialized' => true,
            'stock_quantity' => 0,
        ]);

        // Create PO
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'created_by' => $this->user->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $serializedProduct->id,
            'quantity' => 2,
            'unit_cost' => 100.00,
            'total_cost' => 200.00,
        ]);

        // Receive PO - Fails if no serials provided
        $responseFail = $this->actingAs($this->user)
            ->postJson("http://adv-inv-tenant.prime-erp.local/api/purchase-orders/{$po->id}/receive", [
                'received_items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received' => 2,
                        'serials' => ['SN-123'] // only 1 serial instead of 2
                    ]
                ]
            ]);
        $responseFail->assertStatus(500); // throws exception

        // Receive PO - Succeeds with correct count
        $responseSuccess = $this->actingAs($this->user)
            ->postJson("http://adv-inv-tenant.prime-erp.local/api/purchase-orders/{$po->id}/receive", [
                'received_items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received' => 2,
                        'serials' => ['SN-123', 'SN-456']
                    ]
                ]
            ]);
        $responseSuccess->assertStatus(200);

        tenancy()->initialize($this->tenant);
        $serializedProduct->refresh();
        expect($serializedProduct->stock_quantity)->toBe(2);

        // Verify serial numbers exist in DB in available status
        $serials = ProductSerial::where('product_id', $serializedProduct->id)->get();
        expect($serials)->toHaveCount(2);
        expect($serials->pluck('serial_number')->toArray())->toEqualCanonicalizing(['SN-123', 'SN-456']);
        expect($serials->first()->status)->toBe(ProductSerial::STATUS_AVAILABLE);
    });

    test('can receive and allocate batched products', function () {
        tenancy()->initialize($this->tenant);
        $supplier = Supplier::create(['name' => 'F1']);
        $batchedProduct = Product::factory()->create([
            'warehouse_location_id' => $this->location->id,
            'has_batches' => true,
            'stock_quantity' => 0,
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'branch_id' => $this->branch->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'created_by' => $this->user->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $batchedProduct->id,
            'quantity' => 10,
            'unit_cost' => 10.00,
            'total_cost' => 100.00,
        ]);

        // Receive PO - Fails if no batch number
        $responseFail = $this->actingAs($this->user)
            ->postJson("http://adv-inv-tenant.prime-erp.local/api/purchase-orders/{$po->id}/receive", [
                'received_items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received' => 10,
                    ]
                ]
            ]);
        $responseFail->assertStatus(500);

        // Receive PO - Succeeds
        $responseSuccess = $this->actingAs($this->user)
            ->postJson("http://adv-inv-tenant.prime-erp.local/api/purchase-orders/{$po->id}/receive", [
                'received_items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received' => 10,
                        'batch_number' => 'LOT-2026A',
                        'expiration_date' => '2027-12-31',
                    ]
                ]
            ]);
        $responseSuccess->assertStatus(200);

        tenancy()->initialize($this->tenant);
        $batchedProduct->refresh();
        expect($batchedProduct->stock_quantity)->toBe(10);

        $batch = ProductBatch::where('product_id', $batchedProduct->id)->first();
        expect($batch)->not->toBeNull();
        expect($batch->batch_number)->toBe('LOT-2026A');
        expect((float)$batch->current_quantity)->toBe(10.0);
    });

    test('can allocate serialized and batched items to Order of Service', function () {
        tenancy()->initialize($this->tenant);
        
        // 1. Create serialized and batched products
        $serializedProduct = Product::factory()->create([
            'warehouse_location_id' => $this->location->id,
            'is_serialized' => true,
            'stock_quantity' => 1,
        ]);
        $serial = ProductSerial::create([
            'product_id' => $serializedProduct->id,
            'serial_number' => 'SN-AUTO-1',
            'status' => ProductSerial::STATUS_AVAILABLE,
        ]);

        $batchedProduct = Product::factory()->create([
            'warehouse_location_id' => $this->location->id,
            'has_batches' => true,
            'stock_quantity' => 50,
        ]);
        $batch = ProductBatch::create([
            'product_id' => $batchedProduct->id,
            'batch_number' => 'LOT-AUTO-1',
            'initial_quantity' => 50,
            'current_quantity' => 50,
            'expiration_date' => now()->addYear(),
        ]);

        $order = OrderOfService::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => OrderOfService::STATUS_DRAFT,
        ]);

        $costAllocationService = app(CostAllocationService::class);

        // A. Allocate serialized item - Fails if no serials provided
        expect(function () use ($costAllocationService, $order, $serializedProduct) {
            $costAllocationService->addProductLine($order, new CreateOrderProductLineDTO(
                product_id: $serializedProduct->id,
                quantity: 1,
            ), $this->user->id);
        })->toThrow(\InvalidArgumentException::class);

        // B. Allocate serialized item - Fails if serial is unavailable/invalid
        expect(function () use ($costAllocationService, $order, $serializedProduct) {
            $costAllocationService->addProductLine($order, new CreateOrderProductLineDTO(
                product_id: $serializedProduct->id,
                quantity: 1,
                serials: ['SN-FAKE-999']
            ), $this->user->id);
        })->toThrow(\Exception::class);

        // C. Allocate serialized item - Succeeds
        $lineSerial = $costAllocationService->addProductLine($order, new CreateOrderProductLineDTO(
            product_id: $serializedProduct->id,
            quantity: 1,
            serials: ['SN-AUTO-1']
        ), $this->user->id);

        expect($lineSerial)->toBeInstanceOf(OrderProductLine::class);
        $serial->refresh();
        expect($serial->status)->toBe(ProductSerial::STATUS_RESERVED);
        expect($serial->order_product_line_id)->toBe($lineSerial->id);

        // D. Allocate batched item - Fails if no batch provided
        expect(function () use ($costAllocationService, $order, $batchedProduct) {
            $costAllocationService->addProductLine($order, new CreateOrderProductLineDTO(
                product_id: $batchedProduct->id,
                quantity: 5,
            ), $this->user->id);
        })->toThrow(\InvalidArgumentException::class);

        // E. Allocate batched item - Succeeds
        $lineBatch = $costAllocationService->addProductLine($order, new CreateOrderProductLineDTO(
            product_id: $batchedProduct->id,
            quantity: 5,
            product_batch_id: $batch->id
        ), $this->user->id);

        expect($lineBatch)->toBeInstanceOf(OrderProductLine::class);
        expect($lineBatch->product_batch_id)->toBe($batch->id);

        // Transition order to In Progress
        $order->update(['status' => OrderOfService::STATUS_IN_PROGRESS]);

        // A new line batch inside an in progress order should decrement the batch current quantity immediately
        $lineBatch2 = $costAllocationService->addProductLine($order, new CreateOrderProductLineDTO(
            product_id: $batchedProduct->id,
            quantity: 10,
            product_batch_id: $batch->id
        ), $this->user->id);

        $batch->refresh();
        expect((float)$batch->current_quantity)->toBe(40.0); // 50 - 10

        // F. Remove batch line from in progress order restores batch quantity
        $costAllocationService->removeProductLine($lineBatch2, $this->user->id);
        $batch->refresh();
        expect((float)$batch->current_quantity)->toBe(50.0); // 40 + 10 restored

        // G. Removing serial line restores serial to available status
        $costAllocationService->removeProductLine($lineSerial, $this->user->id);
        $serial->refresh();
        expect($serial->status)->toBe(ProductSerial::STATUS_AVAILABLE);
        expect($serial->order_product_line_id)->toBeNull();
    });
});
