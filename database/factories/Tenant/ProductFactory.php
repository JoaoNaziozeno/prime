<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 50, 5000);
        $costPrice = $unitPrice * $this->faker->randomFloat(2, 0.4, 0.8);

        return [
            'category_id' => ProductCategory::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => 'SKU-' . strtoupper($this->faker->unique()->bothify('??-######')),
            'description' => $this->faker->sentence(),
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'stock_quantity' => $this->faker->numberBetween(0, 1000),
            'min_stock_level' => $this->faker->numberBetween(5, 50),
            'max_stock_level' => $this->faker->numberBetween(500, 2000),
            'unit_of_measure' => $this->faker->randomElement(['UND', 'KG', 'L', 'M', 'M2', 'M3']),
            'is_active' => true,
            'metadata' => [
                'supplier' => $this->faker->company(),
                'weight' => $this->faker->randomFloat(2, 0.1, 100),
            ],
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function lowStock(): self
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => $this->faker->numberBetween(0, 10),
            'min_stock_level' => $this->faker->numberBetween(10, 50),
        ]);
    }

    public function outOfStock(): self
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    public function withHighMargin(): self
    {
        return $this->state(fn (array $attributes) => [
            'unit_price' => $attributes['cost_price'] * 3,
        ]);
    }

    public function forCategory(ProductCategory $category): self
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }
}
