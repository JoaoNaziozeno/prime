<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'cpf' => $this->generateCPF(),
            'cnh' => $this->faker->unique()->numberBetween(10000000000, 99999999999),
            'cnh_category' => $this->faker->randomElement([
                Driver::CNH_CATEGORY_B,
                Driver::CNH_CATEGORY_C,
                Driver::CNH_CATEGORY_D,
                Driver::CNH_CATEGORY_E,
            ]),
            'cnh_expiration' => now()->addYears(5),
            'status' => Driver::STATUS_ACTIVE,
            'hired_at' => now()->subMonths(random_int(1, 24)),
        ];
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Driver::STATUS_ACTIVE,
            ];
        });
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Driver::STATUS_INACTIVE,
            ];
        });
    }

    public function suspended()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Driver::STATUS_SUSPENDED,
            ];
        });
    }

    public function withExpiredCnh()
    {
        return $this->state(function (array $attributes) {
            return [
                'cnh_expiration' => now()->subMonths(3),
            ];
        });
    }

    public function withExpiringCnh()
    {
        return $this->state(function (array $attributes) {
            return [
                'cnh_expiration' => now()->addDays(15),
            ];
        });
    }

    public function categoryB()
    {
        return $this->state(function (array $attributes) {
            return [
                'cnh_category' => Driver::CNH_CATEGORY_B,
            ];
        });
    }

    public function categoryD()
    {
        return $this->state(function (array $attributes) {
            return [
                'cnh_category' => Driver::CNH_CATEGORY_D,
            ];
        });
    }

    protected function generateCPF(): string
    {
        return str_pad(random_int(1, 99999999999), 11, '0', STR_PAD_LEFT);
    }
}
