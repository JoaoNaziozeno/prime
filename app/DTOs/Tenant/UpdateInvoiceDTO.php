<?php

namespace App\DTOs\Tenant;

class UpdateInvoiceDTO
{
    public function __construct(
        public ?string $payment_term_id = null,
        public ?string $payment_method_id = null,
        public ?float $discount_amount = null,
        public ?float $tax_amount = null,
        public ?string $notes = null,
        public ?array $metadata = null,
        public ?string $status = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            payment_term_id: $data['payment_term_id'] ?? null,
            payment_method_id: $data['payment_method_id'] ?? null,
            discount_amount: isset($data['discount_amount']) ? (float) $data['discount_amount'] : null,
            tax_amount: isset($data['tax_amount']) ? (float) $data['tax_amount'] : null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null,
            status: $data['status'] ?? null
        );
    }
}
