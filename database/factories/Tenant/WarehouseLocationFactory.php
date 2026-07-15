<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseLocationFactory extends Factory
{
    protected $model = WarehouseLocation::class;

    public function definition(): array
    {
        $code = 'LOC-' . strtoupper($this->faker->unique()->bothify('??-###'));
        return [
            'name' => 'Prateleira ' . $this->faker->unique()->bothify('#-##'),
            'code' => $code,
            'description' => $this->faker->sentence(),
        ];
    }
}
