<?php

namespace App\Services\Tenant;

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderProductLine;
use App\Models\Tenant\OrderServiceLine;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\PaymentTerm;
use App\Models\Tenant\PaymentMethod;
use App\DTOs\Tenant\CreateInvoiceDTO;
use App\DTOs\Tenant\UpdateInvoiceDTO;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService
{
    /**
     * Create an invoice from an Order of Service
     */
    public function createFromOrder(OrderOfService $order, ?string $paymentTermId = null, ?string $paymentMethodId = null, string $userId): Invoice
    {
        // OS must have approved value or items
        $order->load(['productLines.product', 'serviceLines.service']);

        if ($order->productLines->isEmpty() && $order->serviceLines->isEmpty()) {
            throw new InvalidArgumentException('Não é possível faturar uma OS sem itens de custo (peças/serviços).');
        }

        return DB::connection('tenant')->transaction(function () use ($order, $paymentTermId, $paymentMethodId, $userId) {
            // Determine due date
            $days = 0;
            if ($paymentTermId) {
                $term = PaymentTerm::findOrFail($paymentTermId);
                $days = $term->days_until_due;
            }
            $dueDate = now()->addDays($days);

            // Calculate amounts
            $subtotal = 0.0;
            $serviceBilled = 0.0;

            foreach ($order->productLines as $line) {
                $subtotal += (float) $line->total_price;
            }

            foreach ($order->serviceLines as $line) {
                $subtotal += (float) $line->total_price;
                $serviceBilled += (float) $line->total_price;
            }

            // Calculate ISS tax on service lines (defaults to 5.0%)
            $taxRate = \App\Models\Tenant\Setting::get('tax_iss_rate', 5.0) / 100;
            $taxAmount = $serviceBilled * $taxRate;
            $totalAmount = $subtotal + $taxAmount;

            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad((string)(Invoice::withTrashed()->count() + 1), 6, '0', STR_PAD_LEFT);

            // Create Invoice
            $invoice = Invoice::create([
                'order_of_service_id' => $order->id,
                'customer_id' => $order->customer_id,
                'branch_id' => $order->branch_id,
                'payment_term_id' => $paymentTermId,
                'payment_method_id' => $paymentMethodId,
                'invoice_number' => $invoiceNumber,
                'status' => Invoice::STATUS_DRAFT,
                'issue_date' => now(),
                'due_date' => $dueDate,
                'subtotal_amount' => $subtotal,
                'discount_amount' => 0.0,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0.0,
                'created_by' => $userId,
            ]);

            // Create Invoice Items from Product Lines
            foreach ($order->productLines as $line) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Peça: " . ($line->product->name ?? 'Desconhecida'),
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'total_price' => $line->total_price,
                    'itemable_type' => OrderProductLine::class,
                    'itemable_id' => $line->id,
                ]);
            }

            // Create Invoice Items from Service Lines
            foreach ($order->serviceLines as $line) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Serviço: " . ($line->service->name ?? 'Desconhecido'),
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'total_price' => $line->total_price,
                    'itemable_type' => OrderServiceLine::class,
                    'itemable_id' => $line->id,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * Create a standalone invoice
     */
    public function store(CreateInvoiceDTO $dto, string $userId): Invoice
    {
        return DB::connection('tenant')->transaction(function () use ($dto, $userId) {
            $days = 0;
            if ($dto->payment_term_id) {
                $term = PaymentTerm::findOrFail($dto->payment_term_id);
                $days = $term->days_until_due;
            }
            $dueDate = now()->addDays($days);

            $subtotal = 0.0;
            foreach ($dto->items as $item) {
                $subtotal += (float) ($item['quantity'] * $item['unit_price']);
            }

            $totalAmount = $subtotal - $dto->discount_amount + $dto->tax_amount;
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad((string)(Invoice::withTrashed()->count() + 1), 6, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'order_of_service_id' => $dto->order_of_service_id,
                'customer_id' => $dto->customer_id,
                'branch_id' => $dto->branch_id,
                'payment_term_id' => $dto->payment_term_id,
                'payment_method_id' => $dto->payment_method_id,
                'invoice_number' => $invoiceNumber,
                'status' => Invoice::STATUS_DRAFT,
                'issue_date' => now(),
                'due_date' => $dueDate,
                'subtotal_amount' => $subtotal,
                'discount_amount' => $dto->discount_amount,
                'tax_amount' => $dto->tax_amount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0.0,
                'notes' => $dto->notes,
                'metadata' => $dto->metadata,
                'created_by' => $userId,
            ]);

            foreach ($dto->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                    'itemable_type' => $item['itemable_type'] ?? null,
                    'itemable_id' => $item['itemable_id'] ?? null,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * Update an invoice
     */
    public function update(Invoice $invoice, UpdateInvoiceDTO $dto, string $userId): Invoice
    {
        if (!$invoice->isDraft()) {
            throw new \InvalidArgumentException('Não é possível atualizar uma fatura que não seja rascunho.');
        }

        return DB::connection('tenant')->transaction(function () use ($invoice, $dto, $userId) {
            $data = [];

            if ($dto->payment_term_id !== null) {
                $data['payment_term_id'] = $dto->payment_term_id;
                $term = PaymentTerm::findOrFail($dto->payment_term_id);
                $data['due_date'] = $invoice->issue_date->addDays($term->days_until_due);
            }

            if ($dto->payment_method_id !== null) {
                $data['payment_method_id'] = $dto->payment_method_id;
            }

            if ($dto->notes !== null) {
                $data['notes'] = $dto->notes;
            }

            if ($dto->metadata !== null) {
                $data['metadata'] = $dto->metadata;
            }

            $discount = $dto->discount_amount !== null ? $dto->discount_amount : (float) $invoice->discount_amount;
            $tax = $dto->tax_amount !== null ? $dto->tax_amount : (float) $invoice->tax_amount;

            $data['discount_amount'] = $discount;
            $data['tax_amount'] = $tax;
            $data['total_amount'] = (float) $invoice->subtotal_amount - $discount + $tax;

            if ($dto->status !== null) {
                $data['status'] = $dto->status;
            }

            $invoice->update($data);

            $invoice->setMetadataValue('updated_by', $userId);
            $invoice->setMetadataValue('updated_at', now()->toIso8601String());
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Send an invoice
     */
    public function send(Invoice $invoice, string $userId): bool
    {
        if (!$invoice->canSend()) {
            throw new \InvalidArgumentException('Fatura não está em estado de rascunho para envio.');
        }

        return $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'metadata' => array_merge($invoice->metadata ?? [], [
                'sent_by' => $userId,
                'sent_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Record payment on an invoice
     */
    public function recordPayment(Invoice $invoice, float $amount, string $userId): bool
    {
        if (!$invoice->canRecordPayment()) {
            throw new \InvalidArgumentException('Fatura não está em estado pendente (sent/partially_paid) para receber pagamento.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('O valor do pagamento deve ser maior que zero.');
        }

        return DB::connection('tenant')->transaction(function () use ($invoice, $amount, $userId) {
            $newPaidAmount = (float) $invoice->paid_amount + $amount;
            $totalAmount = (float) $invoice->total_amount;

            $status = Invoice::STATUS_PARTIALLY_PAID;
            if ($newPaidAmount >= $totalAmount) {
                $status = Invoice::STATUS_PAID;
            }

            return $invoice->update([
                'status' => $status,
                'paid_amount' => $newPaidAmount,
                'metadata' => array_merge($invoice->metadata ?? [], [
                    'last_payment_by' => $userId,
                    'last_payment_at' => now()->toIso8601String(),
                ]),
            ]);
        });
    }

    /**
     * Cancel an invoice
     */
    public function cancel(Invoice $invoice, string $userId): bool
    {
        if (!$invoice->canCancel()) {
            throw new \InvalidArgumentException('Esta fatura não pode ser cancelada.');
        }

        return $invoice->update([
            'status' => Invoice::STATUS_CANCELLED,
            'metadata' => array_merge($invoice->metadata ?? [], [
                'cancelled_by' => $userId,
                'cancelled_at' => now()->toIso8601String(),
            ]),
        ]);
    }
}
