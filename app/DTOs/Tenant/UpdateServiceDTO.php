<?php

namespace App\DTOs\Tenant;

class UpdateServiceDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?float $base_price = null,
        public ?float $estimated_hours = null,
        public ?bool $is_active = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            base_price: isset($data['base_price']) ? (float) $data['base_price'] : null,
            estimated_hours: isset($data['estimated_hours']) ? (float) $data['estimated_hours'] : null,
            is_active: $data['is_active'] ?? null,
        );
    }
}
