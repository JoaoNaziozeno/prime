<?php

namespace App\DTOs\Tenant;

class UpdateOrderItemDTO
{
    public function __construct(
        public ?string $description = null,
        public ?string $notes = null,
        public ?int $sequence = null,
        public ?float $quantity = null,
        public ?float $unit_price = null,
        public ?float $estimated_cost = null,
        public ?string $assigned_to = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            description: $data['description'] ?? null,
            notes: $data['notes'] ?? null,
            sequence: $data['sequence'] ?? null,
            quantity: isset($data['quantity']) ? (float) $data['quantity'] : null,
            unit_price: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            estimated_cost: isset($data['estimated_cost']) ? (float) $data['estimated_cost'] : null,
            assigned_to: $data['assigned_to'] ?? null,
        );
    }
}
