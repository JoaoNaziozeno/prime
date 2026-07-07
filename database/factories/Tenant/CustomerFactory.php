<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'cpf_cnpj' => $this->generateCNPJ(),
            'type' => $this->faker->randomElement([Customer::TYPE_INDIVIDUAL, Customer::TYPE_COMPANY]),
            'street' => $this->faker->streetAddress(),
            'number' => $this->faker->buildingNumber(),
            'complement' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'country' => 'BR',
            'zip_code' => $this->faker->postcode(),
            'status' => Customer::STATUS_ACTIVE,
            'activated_at' => now(),
            'metadata' => [
                'source' => 'factory',
                'tags' => ['test'],
            ],
        ];
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Customer::STATUS_ACTIVE,
                'activated_at' => now(),
                'suspended_at' => null,
            ];
        });
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Customer::STATUS_INACTIVE,
                'activated_at' => null,
            ];
        });
    }

    public function suspended()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Customer::STATUS_SUSPENDED,
                'suspended_at' => now(),
            ];
        });
    }

    public function individual()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Customer::TYPE_INDIVIDUAL,
            ];
        });
    }

    public function company()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Customer::TYPE_COMPANY,
            ];
        });
    }

    protected function generateCNPJ(): string
    {
        return str_pad(random_int(1, 999999999999), 14, '0', STR_PAD_LEFT);
    }
}
