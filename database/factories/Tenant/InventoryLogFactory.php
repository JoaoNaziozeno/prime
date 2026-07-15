<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\InventoryLog;
use App\Models\Tenant\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryLogFactory extends Factory
{
    protected $model = InventoryLog::class;

    public function definition(): array
    {
        $product = Product::factory();
        return [
            'product_id' => $product,
            'type' => InventoryLog::TYPE_INBOUND,
            'quantity' => $this->faker->numberBetween(1, 100),
            'balance_after' => $this->faker->numberBetween(100, 200),
            'unit_cost' => $this->faker->randomFloat(2, 50, 500),
            'unit_price' => $this->faker->randomFloat(2, 80, 800),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => $this->faker->sentence(),
        ];
    }

    public function inbound(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => InventoryLog::TYPE_INBOUND,
        ]);
    }

    public function outbound(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => InventoryLog::TYPE_OUTBOUND,
        ]);
    }

    public function adjustment(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => InventoryLog::TYPE_ADJUSTMENT,
        ]);
    }
}
