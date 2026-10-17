<?php

namespace App\Services\Tenant;

use App\Enums\Tenant\CashMovementCategory;
use App\Enums\Tenant\CashMovementType;
use App\Enums\Tenant\CashRegisterType;
use App\Enums\Tenant\CashSessionStatus;
use App\Models\Tenant\CashDenomination;
use App\Models\Tenant\CashRegister;
use App\Models\Tenant\CashSession;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class CashSessionService
{
    /**
     * Abre uma nova sessão de caixa para um operador ou caixa central.
     */
    public function openSession(string $registerId, float $openingBalance, int $userId): CashSession
    {
        return DB::transaction(function () use ($registerId, $openingBalance, $userId) {
            $register = CashRegister::lockForUpdate()->findOrFail($registerId);

            if (!$register->is_active) {
                throw new LogicException('Este ponto de atendimento está desativado.');
            }

            // Verifica se o caixa já possui uma sessão aberta
            $activeSession = $register->activeSession()->first();
            if ($activeSession) {
                throw new LogicException("Este caixa já possui uma sessão aberta (ID: {$activeSession->id}).");
            }

            // Se não for caixa central, verifica se o usuário já tem outra sessão aberta em outro guichê
            if (!$register->is_central) {
                $userOpenSession = CashSession::where('user_id', $userId)
                    ->whereIn('status', [CashSessionStatus::OPEN->value, CashSessionStatus::CLOSING->value])
                    ->first();

                if ($userOpenSession) {
                    throw new LogicException("O usuário já possui uma sessão de caixa aberta em outro ponto.");
                }
            }

            $session = CashSession::create([
                'cash_register_id' => $register->id,
                'user_id' => $userId,
                'status' => CashSessionStatus::OPEN,
                'opened_at' => now(),
                'opening_balance' => $openingBalance,
                'calculated_cash_balance' => $openingBalance,
            ]);

            // Se houver fundo de troco inicial informado e for caixa de usuário, registra movimento de entrada
            if ($openingBalance > 0 && !$register->is_central) {
                $session->movements()->create([
                    'type' => CashMovementType::INFLOW,
                    'category' => CashMovementCategory::CHANGE_SUPPLY,
                    'amount' => $openingBalance,
                    'description' => 'Fundo de troco inicial na abertura de sessão',
                    'created_by' => $userId,
                ]);
            }

            return $session->load(['cashRegister', 'user']);
        });
    }

    /**
     * Obtém a sessão de caixa ativa do usuário logado.
     */
    public function getCurrentSession(int $userId): ?CashSession
    {
        return CashSession::with(['cashRegister', 'movements.paymentMethod', 'outgoingTransfers', 'incomingTransfers'])
            ->where('user_id', $userId)
            ->whereIn('status', [CashSessionStatus::OPEN->value, CashSessionStatus::CLOSING->value])
            ->latest('opened_at')
            ->first();
    }

    /**
     * Obtém a sessão ativa do Caixa Central da filial.
     */
    public function getCentralSession(?int $branchId = null): ?CashSession
    {
        $query = CashRegister::central()->active();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $centralRegister = $query->first();
        if (!$centralRegister) {
            return null;
        }

        return $centralRegister->activeSession()
            ->with(['cashRegister', 'movements.paymentMethod', 'incomingTransfers.sourceSession.user'])
            ->first();
    }

    /**
     * Retorna o resumo consolidado dos valores da sessão (por método de pagamento, sangrias, teto).
     */
    public function getSessionSummary(CashSession $session): array
    {
        $movements = $session->movements()->with('paymentMethod')->get();

        $byPaymentMethod = [];
        $totalInflows = 0.0;
        $totalOutflows = 0.0;
        $cashInflows = 0.0;
        $cashOutflows = 0.0;

        foreach ($movements as $m) {
            $amt = (float) $m->amount;
            $methodCode = $m->paymentMethod?->code ?? 'dinheiro';
            $methodName = $m->paymentMethod?->name ?? 'Dinheiro / Espécie';

            if ($m->type === CashMovementType::INFLOW) {
                $totalInflows += $amt;
                if ($methodCode === 'dinheiro' || $methodCode === 'cash') {
                    $cashInflows += $amt;
                }
                if (!isset($byPaymentMethod[$methodCode])) {
                    $byPaymentMethod[$methodCode] = [
                        'name' => $methodName,
                        'total' => 0.0,
                        'count' => 0,
                    ];
                }
                $byPaymentMethod[$methodCode]['total'] += $amt;
                $byPaymentMethod[$methodCode]['count'] += 1;
            } else {
                $totalOutflows += $amt;
                if ($methodCode === 'dinheiro' || $methodCode === 'cash') {
                    $cashOutflows += $amt;
                }
            }
        }

        $openingBalance = (float) $session->opening_balance;
        $currentCashBalance = $openingBalance + $cashInflows - $cashOutflows;

        // Sangrias pendentes em trânsito
        $pendingBleeds = (float) $session->outgoingTransfers()
            ->where('status', 'pending')
            ->sum('amount');

        $maxLimit = (float) ($session->cashRegister?->max_cash_limit ?? 0);
        $isOverLimit = $maxLimit > 0 && $currentCashBalance >= $maxLimit;
        $limitUsagePercent = $maxLimit > 0 ? round(($currentCashBalance / $maxLimit) * 100, 1) : 0;

        return [
            'session_id' => $session->id,
            'status' => $session->status->value,
            'opened_at' => $session->opened_at,
            'opening_balance' => $openingBalance,
            'total_inflows' => round($totalInflows, 2),
            'total_outflows' => round($totalOutflows, 2),
            'current_cash_balance' => round($currentCashBalance, 2),
            'pending_bleeds' => round($pendingBleeds, 2),
            'max_cash_limit' => $maxLimit,
            'is_over_limit' => $isOverLimit,
            'limit_usage_percent' => $limitUsagePercent,
            'by_payment_method' => array_values($byPaymentMethod),
            'movements_count' => $movements->count(),
        ];
    }

    /**
     * Realiza o Fechamento Cego (Blind Closing):
     * O operador envia o que contou fisicamente (notas, moedas e outros meios).
     * O sistema calcula a diferença matematicamente e registra no log.
     */
    public function closeSessionBlind(
        CashSession $session,
        float $reportedCashBalance,
        array $denominations = [],
        ?string $notes = null,
        int $userId = 0
    ): CashSession {
        return DB::transaction(function () use ($session, $reportedCashBalance, $denominations, $notes, $userId) {
            $session->refresh();

            if ($session->isClosed()) {
                throw new LogicException('Esta sessão de caixa já está fechada.');
            }

            // Impede fechar se houver transferências pendentes de aceite
            $hasPendingTransfers = $session->outgoingTransfers()->where('status', 'pending')->exists();
            if ($hasPendingTransfers) {
                throw new LogicException('Não é possível fechar o caixa enquanto houver sangrias em trânsito pendentes de aceite pelo Caixa Central.');
            }

            // Calcula o saldo real de dinheiro esperado pelo sistema
            $summary = $this->getSessionSummary($session);
            $calculatedCash = (float) $summary['current_cash_balance'];

            // Diferença: se informado > calculado = sobra (positivo). Se informado < calculado = falta (negativo)
            $difference = round($reportedCashBalance - $calculatedCash, 2);

            $status = abs($difference) > 0.05
                ? CashSessionStatus::CONTESTED
                : CashSessionStatus::CLOSED;

            // Salva a discriminação de cédulas e moedas
            foreach ($denominations as $denom) {
                $val = (float) ($denom['denomination'] ?? 0);
                $qty = (int) ($denom['quantity'] ?? 0);
                if ($qty > 0 && $val > 0) {
                    CashDenomination::create([
                        'cash_session_id' => $session->id,
                        'count_type' => 'blind_closing',
                        'denomination' => $val,
                        'quantity' => $qty,
                        'subtotal' => round($val * $qty, 2),
                    ]);
                }
            }

            $session->update([
                'status' => $status,
                'closed_at' => now(),
                'calculated_cash_balance' => $calculatedCash,
                'reported_cash_balance' => $reportedCashBalance,
                'cash_difference' => $difference,
                'closing_notes' => $notes,
                'closed_by' => $userId ?: $session->user_id,
            ]);

            return $session->fresh(['cashRegister', 'denominations']);
        });
    }
}
