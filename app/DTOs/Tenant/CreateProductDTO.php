<?php

namespace App\DTOs\Tenant;

class CreateProductDTO
{
    public function __construct(
        public string $category_id,
        public string $name,
        public string $sku,
        public ?string $description = null,
        public ?float $unit_price = null,
        public ?float $cost_price = null,
        public int $stock_quantity = 0,
        public int $min_stock_level = 10,
        public ?int $max_stock_level = null,
        public string $unit_of_measure = 'UND',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            category_id: $data['category_id'],
            name: $data['name'],
            sku: $data['sku'],
            description: $data['description'] ?? null,
            unit_price: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            cost_price: isset($data['cost_price']) ? (float) $data['cost_price'] : null,
            stock_quantity: (int) ($data['stock_quantity'] ?? 0),
            min_stock_level: (int) ($data['min_stock_level'] ?? 10),
            max_stock_level: isset($data['max_stock_level']) ? (int) $data['max_stock_level'] : null,
            unit_of_measure: $data['unit_of_measure'] ?? 'UND',
        );
    }
}
