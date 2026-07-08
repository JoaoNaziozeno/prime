<?php

namespace App\DTOs\Tenant;

use Carbon\Carbon;

class CreateOrderDTO
{
    public function __construct(
        public string $customer_id,
        public string $branch_id,
        public ?string $vehicle_id = null,
        public ?string $priority = null,
        public ?string $description = null,
        public ?string $internal_notes = null,
        public ?Carbon $expected_end_date = null,
        public ?float $estimated_cost = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            customer_id: $data['customer_id'],
            branch_id: $data['branch_id'],
            vehicle_id: $data['vehicle_id'] ?? null,
            priority: $data['priority'] ?? null,
            description: $data['description'] ?? null,
            internal_notes: $data['internal_notes'] ?? null,
            expected_end_date: isset($data['expected_end_date']) ? Carbon::parse($data['expected_end_date']) : null,
            estimated_cost: isset($data['estimated_cost']) ? (float) $data['estimated_cost'] : null,
        );
    }
}
