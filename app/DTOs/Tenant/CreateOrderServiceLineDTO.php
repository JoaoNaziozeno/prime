<?php

namespace App\DTOs\Tenant;

class CreateOrderServiceLineDTO
{
    public function __construct(
        public string $service_id,
        public ?string $assigned_to = null,
        public float $quantity = 1.0,
        public ?float $unit_price = null,
        public ?float $cost_price = null,
        public ?string $notes = null,
        public ?array $metadata = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            service_id: $data['service_id'],
            assigned_to: $data['assigned_to'] ?? null,
            quantity: (float) ($data['quantity'] ?? 1.0),
            unit_price: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            cost_price: isset($data['cost_price']) ? (float) $data['cost_price'] : null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
