<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Payment;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'gateway' => $this->faker->randomElement(['stripe', 'pix']),
            'gateway_transaction_id' => 'tx_' . uniqid(),
            'status' => Payment::STATUS_PENDING,
            'paid_at' => null,
            'error_message' => null,
            'metadata' => ['env' => 'test'],
            'created_by' => $this->faker->uuid(),
        ];
    }

    public function pending(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Payment::STATUS_PENDING,
            ];
        });
    }

    public function completed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => now(),
            ];
        });
    }

    public function failed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Payment::STATUS_FAILED,
                'error_message' => 'Simulated gateway error',
            ];
        });
    }

    public function refunded(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Payment::STATUS_REFUNDED,
            ];
        });
    }

    public function forInvoice(Invoice $invoice): self
    {
        return $this->state(function (array $attributes) use ($invoice) {
            return [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total_amount,
            ];
        });
    }
}
