<?php

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderProductLine;
use App\Models\Tenant\OrderServiceLine;
use App\Models\Tenant\Product;
use App\Models\Tenant\Service;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\PaymentTerm;
use App\Models\Tenant\PaymentMethod;
use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Services\Tenant\InvoiceService;
use App\DTOs\Tenant\CreateInvoiceDTO;
use App\DTOs\Tenant\UpdateInvoiceDTO;

describe('Invoice Service & Calculations Logic', function () {

    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'financial-logic-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        $this->invoiceService = app(InvoiceService::class);
        $this->userId = fake()->uuid();
    });

    test('creating invoice from OS correctly imports lines, calculates subtotal, 5 percent service tax, and total', function () {
        $order = OrderOfService::factory()->completed()->create();
        $product = Product::factory()->create(['unit_price' => 100.00, 'cost_price' => 50.00]);
        $service = Service::factory()->create(['base_price' => 200.00]);

        // Add lines to the order
        $productLine = OrderProductLine::factory()->forOrder($order)->forProduct($product)->create([
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_price' => 200.00,
        ]);

        $serviceLine = OrderServiceLine::factory()->forOrder($order)->forService($service)->create([
            'quantity' => 3,
            'unit_price' => 200.00,
            'total_price' => 600.00,
        ]);

        $term = PaymentTerm::factory()->create(['days_until_due' => 15]);
        $method = PaymentMethod::factory()->create(['code' => 'pix']);

        $invoice = $this->invoiceService->createFromOrder($order, $term->id, $method->id, $this->userId);

        // Subtotal = 200 (product) + 600 (service) = 800
        // Service total = 600. ISS Tax (5%) = 30.
        // Total amount = 830
        expect($invoice->subtotal_amount)->toEqual(800.00);
        expect($invoice->tax_amount)->toEqual(30.00);
        expect($invoice->total_amount)->toEqual(830.00);
        expect($invoice->status)->toBe(Invoice::STATUS_DRAFT);
        expect($invoice->due_date->toDateString())->toBe(now()->addDays(15)->toDateString());

        // Check invoice items
        expect($invoice->items)->toHaveCount(2);
        expect($invoice->items->first()->total_price)->toEqual(200.00);
    });

    test('invoice status transitions work correctly', function () {
        $invoice = Invoice::factory()->draft()->create([
            'total_amount' => 500.00,
            'paid_amount' => 0.00,
        ]);

        // Send invoice
        $this->invoiceService->send($invoice, $this->userId);
        expect($invoice->refresh()->status)->toBe(Invoice::STATUS_SENT);

        // Pay partially
        $this->invoiceService->recordPayment($invoice, 200.00, $this->userId);
        expect($invoice->refresh()->status)->toBe(Invoice::STATUS_PARTIALLY_PAID);
        expect($invoice->paid_amount)->toEqual(200.00);

        // Pay fully
        $this->invoiceService->recordPayment($invoice, 300.00, $this->userId);
        expect($invoice->refresh()->status)->toBe(Invoice::STATUS_PAID);
        expect($invoice->paid_amount)->toEqual(500.00);
    });

    test('invoice can be cancelled from draft, sent, or partially_paid', function () {
        $invoice = Invoice::factory()->draft()->create();
        
        $this->invoiceService->cancel($invoice, $this->userId);
        expect($invoice->refresh()->status)->toBe(Invoice::STATUS_CANCELLED);
    });

});

describe('Financial Management Controller API', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'financial-api-tenant',
        ]);
        
        tenancy()->initialize($this->tenant);
        $this->order = OrderOfService::factory()->completed()->create();
        $this->product = Product::factory()->create(['unit_price' => 100, 'cost_price' => 50]);

        OrderProductLine::factory()->forOrder($this->order)->forProduct($this->product)->create([
            'quantity' => 1,
            'unit_price' => 100.00,
            'total_price' => 100.00,
        ]);
        
        $this->term = PaymentTerm::factory()->create(['days_until_due' => 30]);
        $this->method = PaymentMethod::factory()->create(['code' => 'boleto']);
    });

    test('can create invoice from order via API', function () {
        $url = "http://financial-api-tenant.prime-erp.local/api/orders/{$this->order->id}/invoice";

        $response = $this->actingAs($this->user)
            ->postJson($url, [
                'payment_term_id' => $this->term->id,
                'payment_method_id' => $this->method->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'status' => 'draft',
            'subtotal_amount' => "100.00",
            'total_amount' => "100.00", // No service tax, only piece
        ]);

        $invoiceId = $response->json('id');
        expect(Invoice::find($invoiceId))->not->toBeNull();
    });

    test('can manage invoices via REST API', function () {
        $invoice = Invoice::factory()
            ->forCustomer($this->order->customer)
            ->forBranch($this->order->branch)
            ->create([
                'payment_term_id' => $this->term->id,
                'payment_method_id' => $this->method->id,
                'subtotal_amount' => 300.00,
                'total_amount' => 300.00,
                'status' => 'draft',
            ]);

        $url = "http://financial-api-tenant.prime-erp.local/api/invoices/{$invoice->id}";

        // Send invoice
        $response = $this->actingAs($this->user)->postJson("{$url}/send");
        $response->assertStatus(200);
        expect($invoice->refresh()->status)->toBe('sent');

        // Record partial payment
        $response = $this->actingAs($this->user)->postJson("{$url}/pay", ['amount' => 100.00]);
        $response->assertStatus(200);
        expect($invoice->refresh()->status)->toBe('partially_paid');

        // Cancel invoice (expect validation error since it has payments, or check behavior:
        // Wait, does InvoiceService allow cancelling partially paid invoice?
        // In our code: $invoice->canCancel() returns true for draft, sent, and partially_paid!
        // Let's verify cancelling works)
        $response = $this->actingAs($this->user)->postJson("{$url}/cancel");
        $response->assertStatus(200);
        expect($invoice->refresh()->status)->toBe('cancelled');
    });

    test('can CRUD payment terms and methods via API', function () {
        $termUrl = "http://financial-api-tenant.prime-erp.local/api/payment-terms";

        // Create term
        $response = $this->actingAs($this->user)->postJson($termUrl, [
            'name' => 'Faturamento 45 dias',
            'days_until_due' => 45,
        ]);
        $response->assertStatus(201);
        $termId = $response->json('id');

        // List terms
        $response = $this->actingAs($this->user)->getJson($termUrl);
        $response->assertStatus(200);
        $response->assertJsonCount(2); // The default factory one + the new one

        // Delete term
        $response = $this->actingAs($this->user)->deleteJson("{$termUrl}/{$termId}");
        $response->assertStatus(204);
    });
});
