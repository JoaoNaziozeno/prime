<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\OrderItem;
use App\Models\Tenant\OrderOfService;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_of_service_id' => OrderOfService::factory(),
            'assigned_to' => null,
            'created_by' => $this->faker->uuid(),
            'description' => $this->faker->sentence(),
            'notes' => $this->faker->optional()->sentence(),
            'sequence' => $this->faker->numberBetween(1, 10),
            'quantity' => $this->faker->randomFloat(2, 1, 10),
            'unit_price' => $this->faker->randomFloat(2, 50, 1000),
            'estimated_cost' => $this->faker->randomFloat(2, 100, 5000),
            'actual_cost' => null,
            'status' => OrderItem::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
            'hours_spent' => null,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function pending(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderItem::STATUS_PENDING,
            ];
        });
    }

    public function inProgress(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderItem::STATUS_IN_PROGRESS,
                'started_at' => now()->subHours(2),
            ];
        });
    }

    public function completed(): self
    {
        return $this->state(function (array $attributes) {
            $startedAt = now()->subHours(4);
            return [
                'status' => OrderItem::STATUS_COMPLETED,
                'started_at' => $startedAt,
                'completed_at' => now(),
                'actual_cost' => $attributes['estimated_cost'] * $this->faker->randomFloat(1, 0.9, 1.1),
                'hours_spent' => $this->faker->randomFloat(2, 1, 8),
            ];
        });
    }

    public function cancelled(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderItem::STATUS_CANCELLED,
            ];
        });
    }

    public function blocked(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderItem::STATUS_BLOCKED,
            ];
        });
    }

    public function forOrder(OrderOfService $order): self
    {
        return $this->state(function (array $attributes) use ($order) {
            return [
                'order_of_service_id' => $order->id,
            ];
        });
    }

    public function assigned($userId): self
    {
        return $this->state(function (array $attributes) use ($userId) {
            return [
                'assigned_to' => $userId,
            ];
        });
    }

    public function withHighCost(): self
    {
        return $this->state(function (array $attributes) {
            $unitPrice = $this->faker->randomFloat(2, 500, 2000);
            return [
                'unit_price' => $unitPrice,
                'estimated_cost' => $unitPrice * $attributes['quantity'],
            ];
        });
    }

    public function withLowCost(): self
    {
        return $this->state(function (array $attributes) {
            $unitPrice = $this->faker->randomFloat(2, 10, 100);
            return [
                'unit_price' => $unitPrice,
                'estimated_cost' => $unitPrice * $attributes['quantity'],
            ];
        });
    }
}
