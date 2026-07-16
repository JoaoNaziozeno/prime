<?php

namespace App\DTOs\Tenant;

class ProcessPaymentDTO
{
    public function __construct(
        public float $amount,
        public string $payment_method_id,
        public string $gateway,
        public array $metadata = []
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            payment_method_id: $data['payment_method_id'],
            gateway: $data['gateway'],
            metadata: $data['metadata'] ?? []
        );
    }
}
