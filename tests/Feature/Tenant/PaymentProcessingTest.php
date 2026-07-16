<?php

use App\Models\Tenant\Invoice;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentMethod;
use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Services\Tenant\PaymentProcessingService;
use App\DTOs\Tenant\ProcessPaymentDTO;

describe('Payment Gateway & Webhook Processing', function () {

    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'payment-logic-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        $this->paymentProcessingService = app(PaymentProcessingService::class);
        $this->userId = fake()->uuid();
        $this->method = PaymentMethod::factory()->create(['code' => 'pix']);
        
        $this->invoice = Invoice::factory()->draft()->create([
            'total_amount' => 1000.00,
            'paid_amount' => 0.00,
        ]);
    });

    test('initiating PIX payment generates a pending payment and returns a QR Code in metadata', function () {
        $dto = new ProcessPaymentDTO(
            amount: 1000.00,
            payment_method_id: $this->method->id,
            gateway: 'pix',
            metadata: []
        );

        $payment = $this->paymentProcessingService->initiatePayment($this->invoice, $dto, $this->userId);

        expect($payment->status)->toBe(Payment::STATUS_PENDING);
        expect($payment->amount)->toEqual(1000.00);
        expect($payment->getMetadataValue('qr_code'))->toContain('br.gov.bcb.pix');
        expect($this->invoice->refresh()->status)->toBe(Invoice::STATUS_SENT); // Sent because it was draft
    });

    test('webhook confirmation processes pending payments, records payment on invoice, and updates status', function () {
        // Setup pending payment
        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->method->id,
            'amount' => 1000.00,
            'gateway' => 'pix',
            'gateway_transaction_id' => 'tx_test_123',
        ]);

        $this->invoice->update(['status' => Invoice::STATUS_SENT]);

        // Trigger Webhook Confirmation
        $this->paymentProcessingService->confirmPayment('tx_test_123', ['status' => 'paid', 'transaction_id' => 'tx_test_123']);

        expect($payment->refresh()->status)->toBe(Payment::STATUS_COMPLETED);
        expect($payment->paid_at)->not->toBeNull();
        expect($this->invoice->refresh()->status)->toBe(Invoice::STATUS_PAID);
        expect($this->invoice->paid_amount)->toEqual(1000.00);
    });

    test('immediate card charge completes synchronously and reconciles invoice directly', function () {
        $cardMethod = PaymentMethod::factory()->create(['code' => 'credit_card']);
        $dto = new ProcessPaymentDTO(
            amount: 1000.00,
            payment_method_id: $cardMethod->id,
            gateway: 'stripe',
            metadata: ['card_number' => '4242']
        );

        $payment = $this->paymentProcessingService->initiatePayment($this->invoice, $dto, $this->userId);

        expect($payment->status)->toBe(Payment::STATUS_COMPLETED);
        expect($this->invoice->refresh()->status)->toBe(Invoice::STATUS_PAID);
        expect($this->invoice->paid_amount)->toEqual(1000.00);
    });

    test('refunding completed payment updates payment status and reverts invoice paid amount', function () {
        $payment = Payment::factory()->completed()->create([
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->method->id,
            'amount' => 400.00,
            'gateway' => 'stripe',
        ]);

        $this->invoice->update([
            'paid_amount' => 400.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        $this->paymentProcessingService->refundPayment($payment, $this->userId);

        expect($payment->refresh()->status)->toBe(Payment::STATUS_REFUNDED);
        expect($payment->getMetadataValue('refund_transaction_id'))->not->toBeNull();
        expect($this->invoice->refresh()->status)->toBe(Invoice::STATUS_SENT); // Reverted back to sent since paid_amount is now 0
        expect($this->invoice->paid_amount)->toEqual(0.00);
    });
});

describe('Payment API Endpoints', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'payment-api-tenant',
        ]);
        
        tenancy()->initialize($this->tenant);
        $this->method = PaymentMethod::factory()->create(['code' => 'pix']);
        
        $this->invoice = Invoice::factory()->draft()->create([
            'total_amount' => 500.00,
            'paid_amount' => 0.00,
        ]);
    });

    test('can initiate payment via REST API', function () {
        $url = "http://payment-api-tenant.prime-erp.local/api/invoices/{$this->invoice->id}/payments";

        $response = $this->actingAs($this->user)->postJson($url, [
            'amount' => 500.00,
            'payment_method_id' => $this->method->id,
            'gateway' => 'pix',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'status' => 'pending',
            'amount' => '500.00',
            'gateway' => 'pix',
        ]);

        $paymentId = $response->json('id');
        expect(Payment::find($paymentId))->not->toBeNull();
    });

    test('can trigger webhook to confirm pending payment', function () {
        $this->invoice->update(['status' => Invoice::STATUS_SENT]);

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->method->id,
            'amount' => 500.00,
            'gateway' => 'pix',
            'gateway_transaction_id' => 'tx_webhook_999',
        ]);

        $url = "http://payment-api-tenant.prime-erp.local/api/payments/webhook/pix";

        $response = $this->postJson($url, [
            'transaction_id' => 'tx_webhook_999',
            'status' => 'completed',
            'event' => 'completed',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'webhook_processed',
        ]);

        expect($payment->refresh()->status)->toBe(Payment::STATUS_COMPLETED);
        expect($this->invoice->refresh()->status)->toBe(Invoice::STATUS_PAID);
    });

    test('can request refund via API', function () {
        $payment = Payment::factory()->completed()->create([
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $this->method->id,
            'amount' => 500.00,
            'gateway' => 'stripe',
        ]);

        $this->invoice->update([
            'paid_amount' => 500.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        $url = "http://payment-api-tenant.prime-erp.local/api/payments/{$payment->id}/refund";

        $response = $this->actingAs($this->user)->postJson($url);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'refunded',
        ]);

        expect($payment->refresh()->status)->toBe(Payment::STATUS_REFUNDED);
        expect($this->invoice->refresh()->status)->toBe(Invoice::STATUS_SENT);
    });
});
