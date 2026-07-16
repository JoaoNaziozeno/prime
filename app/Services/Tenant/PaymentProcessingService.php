<?php

namespace App\Services\Tenant;

use App\Contracts\Tenant\PaymentGatewayInterface;
use App\Services\Tenant\Gateways\StripeGateway;
use App\Services\Tenant\Gateways\PixGateway;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Payment;
use App\DTOs\Tenant\ProcessPaymentDTO;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentProcessingService
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

    /**
     * Resolve the gateway implementation
     */
    public function getGateway(string $gateway): PaymentGatewayInterface
    {
        return match (strtolower($gateway)) {
            'stripe' => new StripeGateway(),
            'pix' => new PixGateway(),
            default => throw new InvalidArgumentException("Gateway '{$gateway}' não suportado."),
        };
    }

    /**
     * Initiate a payment against an invoice
     */
    public function initiatePayment(Invoice $invoice, ProcessPaymentDTO $dto, string $userId): Payment
    {
        if ($invoice->isPaid() || $invoice->isCancelled()) {
            throw new InvalidArgumentException('Não é possível iniciar pagamento para faturas pagas ou canceladas.');
        }

        $remainingAmount = (float) $invoice->total_amount - (float) $invoice->paid_amount;
        if ($dto->amount <= 0 || $dto->amount > $remainingAmount) {
            throw new InvalidArgumentException("Valor do pagamento inválido. Restante: {$remainingAmount}.");
        }

        // If invoice is still draft, automatically send it so it's active
        if ($invoice->isDraft()) {
            $this->invoiceService->send($invoice, $userId);
        }

        return DB::connection('tenant')->transaction(function () use ($invoice, $dto, $userId) {
            // Create payment registry
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_method_id' => $dto->payment_method_id,
                'amount' => $dto->amount,
                'gateway' => $dto->gateway,
                'status' => Payment::STATUS_PENDING,
                'metadata' => $dto->metadata,
                'created_by' => $userId,
            ]);

            // Call gateway
            $gatewayInstance = $this->getGateway($dto->gateway);
            $response = $gatewayInstance->charge($payment);

            if ($response['success']) {
                $payment->gateway_transaction_id = $response['transaction_id'];
                
                if (isset($response['qr_code'])) {
                    $payment->setMetadataValue('qr_code', $response['qr_code']);
                }
                if (isset($response['payment_url'])) {
                    $payment->setMetadataValue('payment_url', $response['payment_url']);
                }

                if ($response['status'] === 'completed') {
                    $payment->complete();
                    $this->invoiceService->recordPayment($invoice, $payment->amount, $userId);
                } else {
                    $payment->save();
                }
            } else {
                $payment->fail($response['error_message'] ?? 'Erro desconhecido no gateway.');
            }

            return $payment;
        });
    }

    /**
     * Confirm a pending payment (called via webhooks)
     */
    public function confirmPayment(string $gatewayTransactionId, array $payload): bool
    {
        return DB::connection('tenant')->transaction(function () use ($gatewayTransactionId, $payload) {
            $payment = Payment::where('gateway_transaction_id', $gatewayTransactionId)
                ->where('status', Payment::STATUS_PENDING)
                ->first();

            if (!$payment) {
                throw new InvalidArgumentException("Pagamento pendente com ID de transação '{$gatewayTransactionId}' não encontrado.");
            }

            // Save payload to metadata
            $payment->setMetadataValue('webhook_payload', $payload);
            $payment->complete();

            // Reconcile Invoice
            $invoice = $payment->invoice;
            $this->invoiceService->recordPayment($invoice, $payment->amount, '00000000-0000-0000-0000-000000000000');

            return true;
        });
    }

    /**
     * Fail a pending payment (called via webhooks or timeout)
     */
    public function failPayment(string $gatewayTransactionId, string $error): bool
    {
        $payment = Payment::where('gateway_transaction_id', $gatewayTransactionId)
            ->where('status', Payment::STATUS_PENDING)
            ->first();

        if (!$payment) {
            throw new InvalidArgumentException("Pagamento pendente com ID de transação '{$gatewayTransactionId}' não encontrado.");
        }

        $payment->fail($error);
        return true;
    }

    /**
     * Refund a completed payment
     */
    public function refundPayment(Payment $payment, string $userId): bool
    {
        if (!$payment->isCompleted()) {
            throw new InvalidArgumentException('Apenas pagamentos concluídos podem ser estornados.');
        }

        return DB::connection('tenant')->transaction(function () use ($payment, $userId) {
            // Call gateway refund
            $gatewayInstance = $this->getGateway($payment->gateway);
            $response = $gatewayInstance->refund($payment);

            if (!$response['success']) {
                throw new \RuntimeException('Estorno recusado pelo gateway de pagamento.');
            }

            // Update payment
            $payment->refund();
            $payment->setMetadataValue('refund_transaction_id', $response['transaction_id']);
            $payment->setMetadataValue('refunded_by', $userId);
            $payment->setMetadataValue('refunded_at', now()->toIso8601String());
            $payment->save();

            // Reconcile Invoice
            $invoice = $payment->invoice;
            $newPaidAmount = (float) $invoice->paid_amount - (float) $payment->amount;

            $status = Invoice::STATUS_SENT;
            if ($newPaidAmount > 0) {
                $status = Invoice::STATUS_PARTIALLY_PAID;
            }

            $invoice->update([
                'paid_amount' => max(0.00, $newPaidAmount),
                'status' => $status,
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'refund_registered_by' => $userId,
                    'refund_registered_at' => now()->toIso8601String(),
                ]),
            ]);

            return true;
        });
    }
}
