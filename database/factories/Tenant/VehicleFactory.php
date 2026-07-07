<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $types = [Vehicle::TYPE_TRUCK, Vehicle::TYPE_VAN, Vehicle::TYPE_CAR];
        $type = $this->faker->randomElement($types);
        $capacity = match($type) {
            Vehicle::TYPE_TRUCK => $this->faker->randomFloat(1, 5, 30),
            Vehicle::TYPE_VAN => $this->faker->randomFloat(1, 2, 5),
            default => null,
        };

        return [
            'plate' => strtoupper($this->faker->unique()->bothify('???-####')),
            'model' => $this->faker->randomElement(['Scania', 'Volvo', 'Mercedes', 'Iveco', 'MAN']),
            'brand' => $this->faker->randomElement(['Scania', 'Volvo', 'Mercedes', 'Iveco', 'MAN']),
            'year' => $this->faker->year(),
            'type' => $type,
            'vin' => strtoupper($this->faker->unique()->bothify('???####################')),
            'color' => $this->faker->colorName(),
            'capacity_tons' => $capacity,
            'renavam' => $this->faker->unique()->numerify('############'),
            'license_expiration' => now()->addYears(2),
            'status' => Vehicle::STATUS_ACTIVE,
            'branch_id' => null,
        ];
    }

    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Vehicle::STATUS_ACTIVE,
            ];
        });
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Vehicle::STATUS_INACTIVE,
            ];
        });
    }

    public function maintenance()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Vehicle::STATUS_MAINTENANCE,
            ];
        });
    }

    public function truck()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Vehicle::TYPE_TRUCK,
                'capacity_tons' => $this->faker->randomFloat(1, 5, 30),
            ];
        });
    }

    public function van()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Vehicle::TYPE_VAN,
                'capacity_tons' => $this->faker->randomFloat(1, 2, 5),
            ];
        });
    }

    public function car()
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Vehicle::TYPE_CAR,
                'capacity_tons' => null,
            ];
        });
    }

    public function withExpiredLicense()
    {
        return $this->state(function (array $attributes) {
            return [
                'license_expiration' => now()->subMonths(3),
            ];
        });
    }

    public function withExpiringLicense()
    {
        return $this->state(function (array $attributes) {
            return [
                'license_expiration' => now()->addDays(15),
            ];
        });
    }

    public function forBranch(Branch $branch)
    {
        return $this->state(function (array $attributes) use ($branch) {
            return [
                'branch_id' => $branch->id,
            ];
        });
    }
}
