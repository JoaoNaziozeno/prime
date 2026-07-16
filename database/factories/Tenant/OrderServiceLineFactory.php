<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\OrderServiceLine;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderServiceLineFactory extends Factory
{
    protected $model = OrderServiceLine::class;

    public function definition(): array
    {
        $service = Service::factory();

        return [
            'order_of_service_id' => OrderOfService::factory(),
            'service_id' => $service,
            'assigned_to' => null,
            'quantity' => $this->faker->randomFloat(2, 1, 10),
            'unit_price' => $this->faker->randomFloat(2, 50, 1000),
            'cost_price' => $this->faker->randomFloat(2, 20, 500),
            'total_price' => function (array $attributes) {
                return $attributes['quantity'] * $attributes['unit_price'];
            },
            'total_cost' => function (array $attributes) {
                return $attributes['quantity'] * $attributes['cost_price'];
            },
            'status' => OrderServiceLine::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
            'hours_spent' => null,
            'notes' => $this->faker->optional()->sentence(),
            'metadata' => [
                'source' => 'factory',
            ],
            'created_by' => $this->faker->uuid(),
        ];
    }

    public function forOrder(OrderOfService $order): self
    {
        return $this->state(function (array $attributes) use ($order) {
            return [
                'order_of_service_id' => $order->id,
            ];
        });
    }

    public function forService(Service $service): self
    {
        return $this->state(function (array $attributes) use ($service) {
            return [
                'service_id' => $service->id,
                'unit_price' => $service->base_price,
            ];
        });
    }

    public function pending(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderServiceLine::STATUS_PENDING,
            ];
        });
    }

    public function inProgress(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderServiceLine::STATUS_IN_PROGRESS,
                'started_at' => now()->subHours(2),
            ];
        });
    }

    public function completed(): self
    {
        return $this->state(function (array $attributes) {
            $startedAt = now()->subHours(4);
            return [
                'status' => OrderServiceLine::STATUS_COMPLETED,
                'started_at' => $startedAt,
                'completed_at' => now(),
                'hours_spent' => $attributes['quantity'],
            ];
        });
    }

    public function cancelled(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderServiceLine::STATUS_CANCELLED,
            ];
        });
    }
}
