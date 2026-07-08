<?php

namespace App\DTOs\Tenant;

class CreateServiceDTO
{
    public function __construct(
        public string $category_id,
        public string $name,
        public string $code,
        public ?string $description = null,
        public ?float $base_price = null,
        public ?float $estimated_hours = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            category_id: $data['category_id'],
            name: $data['name'],
            code: $data['code'],
            description: $data['description'] ?? null,
            base_price: isset($data['base_price']) ? (float) $data['base_price'] : null,
            estimated_hours: isset($data['estimated_hours']) ? (float) $data['estimated_hours'] : null,
        );
    }
}
