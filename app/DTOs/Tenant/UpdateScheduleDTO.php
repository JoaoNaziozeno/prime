<?php

namespace App\DTOs\Tenant;

class UpdateScheduleDTO
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $start_time = null,
        public ?string $end_time = null,
        public ?int $vehicle_id = null,
        public ?string $assigned_to = null,
        public ?string $status = null,
        public ?string $notes = null,
        public ?array $metadata = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            start_time: $data['start_time'] ?? null,
            end_time: $data['end_time'] ?? null,
            vehicle_id: isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            assigned_to: $data['assigned_to'] ?? null,
            status: $data['status'] ?? null,
            notes: $data['notes'] ?? null,
            metadata: $data['metadata'] ?? null
        );
    }
}
