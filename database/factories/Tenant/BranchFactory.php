<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'code' => strtoupper($this->faker->unique()->bothify('??###')),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'street' => $this->faker->streetAddress(),
            'number' => $this->faker->buildingNumber(),
            'complement' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'country' => 'BR',
            'zip_code' => $this->faker->postcode(),
            'status' => Branch::STATUS_ACTIVE,
        ];
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Branch::STATUS_ACTIVE,
            ];
        });
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Branch::STATUS_INACTIVE,
            ];
        });
    }

    public function withCode($code)
    {
        return $this->state(function (array $attributes) use ($code) {
            return [
                'code' => $code,
            ];
        });
    }
}
