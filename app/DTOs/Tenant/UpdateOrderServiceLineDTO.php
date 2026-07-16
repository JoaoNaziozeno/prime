<?php

namespace App\DTOs\Tenant;

class UpdateOrderServiceLineDTO
{
    public function __construct(
        public ?string $assigned_to = null,
        public ?float $quantity = null,
        public ?float $unit_price = null,
        public ?float $cost_price = null,
        public ?string $notes = null,
        public ?array $metadata = null,
        public ?string $status = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            assigned_to: $data['assigned_to'] ?? null,
            quantity: isset($data['quantity']) ? (float) $data['quantity'] : null,
            unit_price: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            cost_price: isset($data['cost_price']) ? (float) $data['cost_price'] : null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null,
            status: $data['status'] ?? null,
        );
    }
}
