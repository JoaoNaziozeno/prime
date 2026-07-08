<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderOfServiceFactory extends Factory
{
    protected $model = OrderOfService::class;

    public function definition(): array
    {
        $customer = Customer::factory();
        $branch = Branch::factory();

        return [
            'customer_id' => $customer,
            'vehicle_id' => Vehicle::factory(),
            'branch_id' => $branch,
            'created_by' => $this->faker->uuid(),
            'status' => OrderOfService::STATUS_DRAFT,
            'priority' => $this->faker->randomElement([
                OrderOfService::PRIORITY_LOW,
                OrderOfService::PRIORITY_MEDIUM,
                OrderOfService::PRIORITY_HIGH,
                OrderOfService::PRIORITY_CRITICAL,
            ]),
            'description' => $this->faker->sentences(3, true),
            'internal_notes' => $this->faker->optional()->sentence(),
            'start_date' => null,
            'expected_end_date' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
            'actual_end_date' => null,
            'estimated_cost' => $this->faker->randomFloat(2, 100, 5000),
            'actual_cost' => null,
            'approved_amount' => null,
            'reference_number' => 'OS-' . strtoupper($this->faker->unique()->bothify('??-######')),
            'metadata' => [
                'source' => 'factory',
                'tags' => ['test'],
            ],
        ];
    }

    public function draft(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderOfService::STATUS_DRAFT,
            ];
        });
    }

    public function pendingApproval(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderOfService::STATUS_PENDING_APPROVAL,
            ];
        });
    }

    public function approved(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderOfService::STATUS_APPROVED,
                'approved_amount' => $attributes['estimated_cost'],
            ];
        });
    }

    public function inProgress(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderOfService::STATUS_IN_PROGRESS,
                'start_date' => now(),
                'approved_amount' => $attributes['estimated_cost'],
            ];
        });
    }

    public function completed(): self
    {
        return $this->state(function (array $attributes) {
            $startDate = now()->subDays(5);
            return [
                'status' => OrderOfService::STATUS_COMPLETED,
                'start_date' => $startDate,
                'expected_end_date' => $startDate->addDays(3),
                'actual_end_date' => now(),
                'actual_cost' => $attributes['estimated_cost'] * $this->faker->randomFloat(1, 0.8, 1.3),
                'approved_amount' => $attributes['estimated_cost'],
            ];
        });
    }

    public function cancelled(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderOfService::STATUS_CANCELLED,
            ];
        });
    }

    public function onHold(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderOfService::STATUS_ON_HOLD,
                'start_date' => now()->subDays(2),
                'approved_amount' => $attributes['estimated_cost'],
            ];
        });
    }

    public function highPriority(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'priority' => OrderOfService::PRIORITY_HIGH,
            ];
        });
    }

    public function criticalPriority(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'priority' => OrderOfService::PRIORITY_CRITICAL,
            ];
        });
    }

    public function forCustomer(Customer $customer): self
    {
        return $this->state(function (array $attributes) use ($customer) {
            return [
                'customer_id' => $customer->id,
            ];
        });
    }

    public function forVehicle(Vehicle $vehicle): self
    {
        return $this->state(function (array $attributes) use ($vehicle) {
            return [
                'vehicle_id' => $vehicle->id,
            ];
        });
    }

    public function forBranch(Branch $branch): self
    {
        return $this->state(function (array $attributes) use ($branch) {
            return [
                'branch_id' => $branch->id,
            ];
        });
    }

    public function withHighCost(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'estimated_cost' => $this->faker->randomFloat(2, 5000, 50000),
            ];
        });
    }

    public function withLowCost(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'estimated_cost' => $this->faker->randomFloat(2, 50, 500),
            ];
        });
    }
}
