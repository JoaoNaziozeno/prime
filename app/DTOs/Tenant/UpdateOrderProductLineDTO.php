<?php

namespace App\DTOs\Tenant;

class UpdateOrderProductLineDTO
{
    public function __construct(
        public ?float $quantity = null,
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
            quantity: isset($data['quantity']) ? (float) $data['quantity'] : null,
            unit_price: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            cost_price: isset($data['cost_price']) ? (float) $data['cost_price'] : null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null,
            serials: $data['serials'] ?? null,
            product_batch_id: $data['product_batch_id'] ?? null,
        );
    }
}
