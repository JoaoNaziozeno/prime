<?php

namespace App\Services\Tenant;

use App\Enums\Tenant\CashMovementCategory;
use App\Enums\Tenant\CashMovementType;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\CashSession;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentMethod;
use Illuminate\Support\Facades\DB;
use LogicException;

class CashMovementService
{
    /**
     * Recebe o pagamento de uma Ordem de Serviço, suportando divisão em múltiplos meios de pagamento (Split).
     * Exemplo de $splits:
     * [
     *   ['payment_method_id' => 'uuid-dinheiro', 'amount' => 500.00, 'metadata' => []],
     *   ['payment_method_id' => 'uuid-pix', 'amount' => 1500.00, 'metadata' => ['e2e' => '...']],
     *   ['payment_method_id' => 'uuid-cartao', 'amount' => 3000.00, 'metadata' => ['nsu' => '12345', 'card_brand' => 'mastercard']]
     * ]
     */
    public function receiveOrderOfServicePayment(
        CashSession $session,
        OrderOfService $order,
        array $splits,
        int $userId = 0
    ): array {
        return DB::transaction(function () use ($session, $order, $splits, $userId) {
            if (!$session->isOpen()) {
                throw new LogicException('A sessão de caixa informada precisa estar aberta para receber pagamentos.');
            }

            if (empty($splits)) {
                throw new LogicException('Informe ao menos uma forma de pagamento para liquidar a O.S.');
            }

            // Localiza ou cria a fatura vinculada à O.S
            $invoice = Invoice::where('order_of_service_id', $order->id)->first();
            $totalOS = (float) ($order->approved_amount > 0 ? $order->approved_amount : $order->estimated_cost);

            if (!$invoice) {
                $invoice = Invoice::create([
                    'order_of_service_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'branch_id' => $order->branch_id ?? 1,
                    'invoice_number' => 'INV-' . strtoupper(substr($order->id, 0, 8)),
                    'status' => 'draft',
                    'issue_date' => now(),
                    'due_date' => now(),
                    'subtotal_amount' => $totalOS,
                    'total_amount' => $totalOS,
                    'paid_amount' => 0,
                    'created_by' => (string) $userId,
                ]);
            }

            $createdMovements = [];
            $totalPaidInThisTransaction = 0.0;

            foreach ($splits as $split) {
                $amount = (float) ($split['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $methodId = $split['payment_method_id'] ?? null;
                $method = $methodId ? PaymentMethod::find($methodId) : null;
                $methodName = $method?->name ?? 'Diversos';

                // 1. Cria o registro de movimentação no Caixa do Operador
                $movement = CashMovement::create([
                    'cash_session_id' => $session->id,
                    'order_of_service_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'payment_method_id' => $methodId,
                    'type' => CashMovementType::INFLOW,
                    'category' => CashMovementCategory::OS_PAYMENT,
                    'amount' => $amount,
                    'description' => sprintf(
                        'Recebimento O.S #%s via %s (Cliente: %s)',
                        $order->reference_number ?? substr($order->id, 0, 8),
                        $methodName,
                        $order->customer?->name ?? 'Geral'
                    ),
                    'receipt_number' => $invoice->invoice_number,
                    'metadata' => $split['metadata'] ?? null,
                    'created_by' => $userId,
                ]);

                // 2. Cria o registro formal na tabela payments
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'payment_method_id' => $methodId,
                    'amount' => $amount,
                    'gateway' => $method?->code ?? 'cash',
                    'gateway_transaction_id' => $split['metadata']['nsu'] ?? $split['metadata']['transaction_id'] ?? null,
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                    'created_by' => (string) $userId,
                    'metadata' => array_merge($split['metadata'] ?? [], [
                        'cash_session_id' => $session->id,
                        'cash_movement_id' => $movement->id,
                    ]),
                ]);

                $totalPaidInThisTransaction += $amount;
                $createdMovements[] = $movement;
            }

            // 3. Atualiza os saldos da Fatura
            $newPaidAmount = round((float) $invoice->paid_amount + $totalPaidInThisTransaction, 2);
            $invoiceTotal = (float) $invoice->total_amount;

            $newStatus = ($newPaidAmount >= $invoiceTotal && $invoiceTotal > 0)
                ? 'paid'
                : 'partially_paid';

            $invoice->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
            ]);

            return [
                'invoice' => $invoice->fresh(),
                'total_paid_now' => $totalPaidInThisTransaction,
                'movements' => $createdMovements,
            ];
        });
    }

    /**
     * Registra uma despesa miúda de balcão (pequenas saídas de caixa autorizadas).
     */
    public function registerPettyExpense(
        CashSession $session,
        float $amount,
        string $description,
        ?string $receiptNumber = null,
        int $userId = 0
    ): CashMovement {
        return DB::transaction(function () use ($session, $amount, $description, $receiptNumber, $userId) {
            if (!$session->isOpen()) {
                throw new LogicException('A sessão de caixa precisa estar aberta.');
            }

            if ($amount <= 0) {
                throw new LogicException('O valor da despesa deve ser maior que zero.');
            }

            $availableCash = $session->getCurrentCashBalance();
            if ($amount > $availableCash) {
                throw new LogicException(sprintf(
                    'Saldo em dinheiro insuficiente na gaveta. Saldo atual: R$ %s | Solicitado: R$ %s',
                    number_format($availableCash, 2, ',', '.'),
                    number_format($amount, 2, ',', '.')
                ));
            }

            return CashMovement::create([
                'cash_session_id' => $session->id,
                'type' => CashMovementType::OUTFLOW,
                'category' => CashMovementCategory::PETTY_EXPENSE,
                'amount' => $amount,
                'description' => $description,
                'receipt_number' => $receiptNumber,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Registra um suprimento avulso na sessão de caixa.
     */
    public function registerSupply(
        CashSession $session,
        float $amount,
        string $description,
        int $userId = 0
    ): CashMovement {
        return DB::transaction(function () use ($session, $amount, $description, $userId) {
            if (!$session->isOpen()) {
                throw new LogicException('A sessão de caixa precisa estar aberta.');
            }

            if ($amount <= 0) {
                throw new LogicException('O valor do suprimento deve ser maior que zero.');
            }

            return CashMovement::create([
                'cash_session_id' => $session->id,
                'type' => CashMovementType::INFLOW,
                'category' => CashMovementCategory::CHANGE_SUPPLY,
                'amount' => $amount,
                'description' => $description,
                'created_by' => $userId,
            ]);
        });
    }
}
