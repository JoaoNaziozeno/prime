<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\PaymentTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentTermFactory extends Factory
{
    protected $model = PaymentTerm::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['À vista', '15 Dias', '30 Dias', '30/60 Dias']),
            'days_until_due' => $this->faker->randomElement([0, 15, 30, 60]),
            'is_active' => true,
        ];
    }
}
