<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Document;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'documentable_type' => Vehicle::class,
            'documentable_id' => Vehicle::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'file_path' => 'tenants/test-tenant/documents/cnh/' . $this->faker->uuid() . '.pdf',
            'file_name' => 'cnh_scan.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 1024 * rand(50, 500),
            'document_type' => 'cnh',
            'expires_at' => now()->addDays(rand(10, 90)),
            'metadata' => ['test' => true],
            'created_by' => $this->faker->uuid(),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(rand(1, 30)),
        ]);
    }

    public function expiringSoon(): self
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays(rand(1, 15)),
        ]);
    }
}
