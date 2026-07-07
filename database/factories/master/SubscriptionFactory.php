<?php

namespace Database\Factories\Master;

use App\Models\Master\Company;
use App\Models\Master\Plan;
use App\Models\Master\Subscription;
use App\Models\Master\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $plan = Plan::factory();

        return [
            'company_id' => Company::factory(),
            'plan_id' => $plan,
            'status' => 'active',
            'started_at' => now(),
            'renews_at' => now()->addDays(30),
            'cancelled_at' => null,
            'trial_ends_at' => now()->addDays(14),
            'current_amount' => 299.00,
            'payment_method' => 'credit_card',
            'payment_reference' => $this->faker->uuid(),
            'payment_retries' => 0,
            'notes' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Assinatura ativa
     */
    public function active(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'started_at' => now()->subDays(10),
                'renews_at' => now()->addDays(20),
            ];
        });
    }

    /**
     * Assinatura pausada
     */
    public function paused(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'paused',
            ];
        });
    }

    /**
     * Assinatura cancelada
     */
    public function cancelled(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ];
        });
    }

    /**
     * Assinatura em período de trial
     */
    public function onTrial(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'trial_ends_at' => now()->addDays(7),
                'started_at' => now()->subDays(7),
            ];
        });
    }

    /**
     * Assinatura vencendo em breve
     */
    public function renewingSoon(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'renews_at' => now()->addDays(3),
            ];
        });
    }

    /**
     * Assinatura com Boleto
     */
    public function withBoleto(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_method' => 'boleto',
            ];
        });
    }

    /**
     * Assinatura com PIX
     */
    public function withPix(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_method' => 'pix',
            ];
        });
    }
}
