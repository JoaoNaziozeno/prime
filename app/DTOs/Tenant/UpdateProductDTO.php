<?php

namespace App\DTOs\Tenant;

class UpdateProductDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?float $unit_price = null,
        public ?float $cost_price = null,
        public ?int $min_stock_level = null,
        public ?int $max_stock_level = null,
        public ?bool $is_active = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            unit_price: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            cost_price: isset($data['cost_price']) ? (float) $data['cost_price'] : null,
            min_stock_level: isset($data['min_stock_level']) ? (int) $data['min_stock_level'] : null,
            max_stock_level: isset($data['max_stock_level']) ? (int) $data['max_stock_level'] : null,
            is_active: $data['is_active'] ?? null,
        );
    }
}
