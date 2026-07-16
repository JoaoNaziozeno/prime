<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Schedule;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\OrderOfService;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $start = now()->addDays(rand(1, 10))->setHour(rand(8, 16))->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(rand(1, 4));

        return [
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'vehicle_id' => Vehicle::factory(),
            'order_of_service_id' => null,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => Schedule::STATUS_SCHEDULED,
            'assigned_to' => $this->faker->uuid(),
            'notes' => $this->faker->sentence(),
            'metadata' => ['test' => true],
            'created_by' => $this->faker->uuid(),
        ];
    }

    public function status(string $status): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
