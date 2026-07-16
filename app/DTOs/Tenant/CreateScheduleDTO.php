<?php

namespace App\DTOs\Tenant;

class CreateScheduleDTO
{
    public function __construct(
        public int $branch_id,
        public int $customer_id,
        public string $title,
        public string $start_time,
        public string $end_time,
        public ?int $vehicle_id = null,
        public ?string $order_of_service_id = null,
        public ?string $description = null,
        public ?string $assigned_to = null,
        public ?string $notes = null,
        public array $metadata = []
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            branch_id: (int) $data['branch_id'],
            customer_id: (int) $data['customer_id'],
            title: $data['title'],
            start_time: $data['start_time'],
            end_time: $data['end_time'],
            vehicle_id: isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            order_of_service_id: $data['order_of_service_id'] ?? null,
            description: $data['description'] ?? null,
            assigned_to: $data['assigned_to'] ?? null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }
}
