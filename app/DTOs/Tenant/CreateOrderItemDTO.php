<?php

namespace App\DTOs\Tenant;

class CreateOrderItemDTO
{
    public function __construct(
        public string $order_of_service_id,
        public string $description,
        public ?string $notes = null,
        public ?int $sequence = null,
        public float $quantity = 1,
        public float $unit_price = 0,
        public ?float $estimated_cost = null,
        public ?string $assigned_to = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            order_of_service_id: $data['order_of_service_id'],
            description: $data['description'],
            notes: $data['notes'] ?? null,
            sequence: $data['sequence'] ?? null,
            quantity: (float) ($data['quantity'] ?? 1),
            unit_price: (float) ($data['unit_price'] ?? 0),
            estimated_cost: isset($data['estimated_cost']) ? (float) $data['estimated_cost'] : null,
            assigned_to: $data['assigned_to'] ?? null,
        );
    }
}
