<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        $name = $this->faker->word() . ' ' . $this->faker->word() . ' ' . rand(1, 9999);
        return [
            'name' => $name,
            'code' => strtolower(str_replace(' ', '_', $name)) . '_' . uniqid(),
            'is_active' => true,
        ];
    }
}
