<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $qty = $this->faker->randomFloat(2, 1, 5);
        $price = $this->faker->randomFloat(2, 10, 500);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => $this->faker->words(3, true),
            'quantity' => $qty,
            'unit_price' => $price,
            'total_price' => $qty * $price,
            'itemable_type' => null,
            'itemable_id' => null,
        ];
    }

    public function forInvoice(Invoice $invoice): self
    {
        return $this->state(function (array $attributes) use ($invoice) {
            return [
                'invoice_id' => $invoice->id,
            ];
        });
    }
}
