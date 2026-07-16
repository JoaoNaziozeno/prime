<?php

namespace App\Contracts\Tenant;

use App\Models\Tenant\Payment;

interface PaymentGatewayInterface
{
    /**
     * Charge a payment.
     * Returns an array containing gateway details:
     * [
     *     'success' => bool,
     *     'transaction_id' => string,
     *     'status' => string, // completed, pending, failed
     *     'error_message' => ?string,
     *     'qr_code' => ?string, // for PIX
     *     'payment_url' => ?string, // for Stripe/Boleto
     * ]
     */
    public function charge(Payment $payment): array;

    /**
     * Refund a payment.
     * Returns gateway refund details:
     * [
     *     'success' => bool,
     *     'transaction_id' => string,
     * ]
     */
    public function refund(Payment $payment): array;
}
