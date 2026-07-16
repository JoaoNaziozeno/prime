<?php

namespace Database\Factories\Master;

use App\Models\Master\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word() . ' Plan ' . uniqid(),
            'description' => $this->faker->sentence(),
            'type' => 'monthly',
            'price' => $this->faker->randomFloat(2, 99, 999),
            'billing_cycle_days' => 30,
            'max_users' => $this->faker->numberBetween(5, 50),
            'max_branches' => $this->faker->numberBetween(1, 10),
            'max_storage_gb' => $this->faker->numberBetween(10, 1000),
            'has_api_access' => $this->faker->boolean(),
            'has_support' => true,
            'features' => json_encode(['feature_1', 'feature_2']),
            'is_active' => true,
            'trial_days' => 14,
        ];
    }

    /**
     * Plano básico
     */
    public function basic(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Plano Básico',
                'price' => 99.00,
                'max_users' => 5,
                'max_branches' => 1,
                'max_storage_gb' => 10,
                'has_api_access' => false,
            ];
        });
    }

    /**
     * Plano profissional
     */
    public function professional(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Plano Profissional',
                'price' => 299.00,
                'max_users' => 25,
                'max_branches' => 5,
                'max_storage_gb' => 100,
                'has_api_access' => true,
            ];
        });
    }

    /**
     * Plano empresarial
     */
    public function enterprise(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Plano Empresarial',
                'price' => 999.00,
                'max_users' => 100,
                'max_branches' => 50,
                'max_storage_gb' => 1000,
                'has_api_access' => true,
            ];
        });
    }

    /**
     * Plano anual
     */
    public function yearly(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'yearly',
                'billing_cycle_days' => 365,
            ];
        });
    }

    /**
     * Plano inativo
     */
    public function inactive(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }
}
