<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\PaymentTerm;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\OrderOfService;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 5000);
        $tax = $subtotal * 0.05;
        $total = $subtotal + $tax;

        return [
            'order_of_service_id' => null,
            'customer_id' => Customer::factory(),
            'branch_id' => Branch::factory(),
            'payment_term_id' => PaymentTerm::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'invoice_number' => 'INV-' . $this->faker->unique()->numberBetween(2026000000, 2026999999),
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal_amount' => $subtotal,
            'discount_amount' => 0.0,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'paid_amount' => 0.0,
            'notes' => $this->faker->sentence(),
            'metadata' => ['source' => 'factory'],
            'created_by' => $this->faker->uuid(),
        ];
    }

    public function draft(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Invoice::STATUS_DRAFT,
            ];
        });
    }

    public function sent(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Invoice::STATUS_SENT,
            ];
        });
    }

    public function paid(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Invoice::STATUS_PAID,
                'paid_amount' => $attributes['total_amount'],
            ];
        });
    }

    public function cancelled(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Invoice::STATUS_CANCELLED,
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

    public function forBranch(Branch $branch): self
    {
        return $this->state(function (array $attributes) use ($branch) {
            return [
                'branch_id' => $branch->id,
            ];
        });
    }

    public function forOrder(OrderOfService $order): self
    {
        return $this->state(function (array $attributes) use ($order) {
            return [
                'order_of_service_id' => $order->id,
                'customer_id' => $order->customer_id,
                'branch_id' => $order->branch_id,
            ];
        });
    }
}
