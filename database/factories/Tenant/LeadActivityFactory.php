<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\LeadActivity;
use App\Models\Tenant\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadActivityFactory extends Factory
{
    protected $model = LeadActivity::class;

    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'type' => $this->faker->randomElement(['call', 'meeting', 'email', 'task', 'note']),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'due_date' => null,
            'completed_at' => null,
            'created_by' => 1,
        ];
    }
}
