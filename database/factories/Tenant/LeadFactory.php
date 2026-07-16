<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Lead;
use App\Models\Tenant\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => $this->faker->name(),
            'company_name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'source' => $this->faker->randomElement(['web', 'referral', 'cold_call']),
            'status' => Lead::STATUS_NEW,
            'estimated_value' => $this->faker->randomFloat(2, 1000, 50000),
            'assigned_to' => null,
            'converted_customer_id' => null,
            'notes' => $this->faker->sentence(),
            'created_by' => 1,
        ];
    }
}
