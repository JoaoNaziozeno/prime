<?php

namespace App\Services\Tenant\Gateways;

use App\Contracts\Tenant\PaymentGatewayInterface;
use App\Models\Tenant\Payment;

class PixGateway implements PaymentGatewayInterface
{
    public function charge(Payment $payment): array
    {
        $transactionId = 'tx_pix_' . uniqid();

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => 'pending',
            'error_message' => null,
            'qr_code' => '00020126580014br.gov.bcb.pix0136' . uniqid() . '5204000053039865405' . $payment->amount . '5802BR5913Prime ERP SaaS6008BRASILIA62070503***6304' . sprintf('%04X', rand(0, 65535)),
        ];
    }

    public function refund(Payment $payment): array
    {
        return [
            'success' => true,
            'transaction_id' => 're_pix_' . uniqid(),
        ];
    }
}
