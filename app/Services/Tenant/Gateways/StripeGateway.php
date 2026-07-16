<?php

namespace App\Services\Tenant\Gateways;

use App\Contracts\Tenant\PaymentGatewayInterface;
use App\Models\Tenant\Payment;

class StripeGateway implements PaymentGatewayInterface
{
    public function charge(Payment $payment): array
    {
        $metadata = $payment->metadata ?? [];
        $cardNumber = $metadata['card_number'] ?? '4242';

        if (str_ends_with($cardNumber, '6666')) {
            return [
                'success' => false,
                'transaction_id' => 'ch_stripe_failed_' . uniqid(),
                'status' => 'failed',
                'error_message' => 'Cartão recusado: saldo insuficiente.',
                'payment_url' => null,
            ];
        }

        return [
            'success' => true,
            'transaction_id' => 'ch_stripe_success_' . uniqid(),
            'status' => 'completed',
            'error_message' => null,
            'payment_url' => 'https://checkout.stripe.com/pay/' . uniqid(),
        ];
    }

    public function refund(Payment $payment): array
    {
        return [
            'success' => true,
            'transaction_id' => 're_stripe_' . uniqid(),
        ];
    }
}
