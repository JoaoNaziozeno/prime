<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Service;
use App\Models\Tenant\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'category_id' => ServiceCategory::factory(),
            'name' => $this->faker->words(3, true),
            'code' => 'SRV-' . strtoupper($this->faker->unique()->bothify('??-#####')),
            'description' => $this->faker->sentence(),
            'base_price' => $this->faker->randomFloat(2, 50, 1000),
            'estimated_hours' => $this->faker->randomFloat(2, 0.5, 8),
            'is_active' => true,
            'metadata' => [
                'tags' => [$this->faker->word(), $this->faker->word()],
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

    public function forCategory(ServiceCategory $category): self
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    public function quickService(): self
    {
        return $this->state(fn (array $attributes) => [
            'estimated_hours' => $this->faker->randomFloat(2, 0.25, 2),
        ]);
    }

    public function complexService(): self
    {
        return $this->state(fn (array $attributes) => [
            'estimated_hours' => $this->faker->randomFloat(2, 8, 40),
            'base_price' => $this->faker->randomFloat(2, 500, 5000),
        ]);
    }
}
