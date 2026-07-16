<?php

namespace App\DTOs\Tenant;

class CreateInvoiceDTO
{
    public function __construct(
        public ?string $order_of_service_id,
        public int $customer_id,
        public int $branch_id,
        public ?string $payment_term_id = null,
        public ?string $payment_method_id = null,
        public float $discount_amount = 0.0,
        public float $tax_amount = 0.0,
        public ?string $notes = null,
        public ?array $metadata = null,
        public array $items = []
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            order_of_service_id: $data['order_of_service_id'] ?? null,
            customer_id: (int) $data['customer_id'],
            branch_id: (int) $data['branch_id'],
            payment_term_id: $data['payment_term_id'] ?? null,
            payment_method_id: $data['payment_method_id'] ?? null,
            discount_amount: (float) ($data['discount_amount'] ?? 0.0),
            tax_amount: (float) ($data['tax_amount'] ?? 0.0),
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null,
            items: $data['items'] ?? []
        );
    }
}
