<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\OrderProductLine;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderProductLineFactory extends Factory
{
    protected $model = OrderProductLine::class;

    public function definition(): array
    {
        $product = Product::factory();

        return [
            'order_of_service_id' => OrderOfService::factory(),
            'product_id' => $product,
            'quantity' => $this->faker->randomFloat(2, 1, 10),
            'unit_price' => $this->faker->randomFloat(2, 50, 1000),
            'cost_price' => $this->faker->randomFloat(2, 20, 500),
            'total_price' => function (array $attributes) {
                return $attributes['quantity'] * $attributes['unit_price'];
            },
            'total_cost' => function (array $attributes) {
                return $attributes['quantity'] * $attributes['cost_price'];
            },
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

    public function forProduct(Product $product): self
    {
        return $this->state(function (array $attributes) use ($product) {
            return [
                'product_id' => $product->id,
                'unit_price' => $product->unit_price,
                'cost_price' => $product->cost_price,
            ];
        });
    }
}
