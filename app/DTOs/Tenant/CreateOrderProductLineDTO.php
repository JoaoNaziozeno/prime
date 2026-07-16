<?php

namespace App\DTOs\Tenant;

class CreateOrderProductLineDTO
{
    public function __construct(
        public string $product_id,
        public float $quantity = 1.0,
        public ?float $unit_price = null,
        public ?float $cost_price = null,
        public ?string $notes = null,
        public ?array $metadata = null,
        public ?array $serials = null,
        public ?string $product_batch_id = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            product_id: $data['product_id'],
            quantity: (float) ($data['quantity'] ?? 1.0),
            unit_price: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            cost_price: isset($data['cost_price']) ? (float) $data['cost_price'] : null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null,
            serials: $data['serials'] ?? null,
            product_batch_id: $data['product_batch_id'] ?? null,
        );
    }
}
