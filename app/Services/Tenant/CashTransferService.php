<?php

namespace App\Services\Tenant;

use App\Enums\Tenant\CashMovementCategory;
use App\Enums\Tenant\CashMovementType;
use App\Enums\Tenant\CashTransferStatus;
use App\Enums\Tenant\CashTransferType;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\CashSession;
use App\Models\Tenant\CashTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class CashTransferService
{
    /**
     * Solicita uma transferência entre caixas (ex: Sangria para o Caixa Central, Suprimento, ou P2P).
     * O status inicial é PENDING (em trânsito) aguardando o duplo aceite da ponta recebedora.
     */
    public function requestTransfer(
        CashSession $sourceSession,
        CashSession $destinationSession,
        float $amount,
        CashTransferType $type,
        ?string $notes = null,
        int $userId = 0
    ): CashTransfer {
        return DB::transaction(function () use ($sourceSession, $destinationSession, $amount, $type, $notes, $userId) {
            if ($amount <= 0) {
                throw new LogicException('O valor da transferência deve ser maior que zero.');
            }

            if ($sourceSession->id === $destinationSession->id) {
                throw new LogicException('O caixa de origem e destino não podem ser o mesmo.');
            }

            if (!$sourceSession->isOpen()) {
                throw new LogicException('O caixa de origem precisa estar aberto para transferir valores.');
            }

            if (!$destinationSession->isOpen()) {
                throw new LogicException('O caixa de destino precisa estar aberto para receber valores.');
            }

            // Valida saldo disponível na gaveta de origem
            $availableCash = $sourceSession->getCurrentCashBalance();
            if ($amount > $availableCash) {
                throw new LogicException(sprintf(
                    'Saldo insuficiente em gaveta para sangria/transferência. Disponível: R$ %s | Solicitado: R$ %s',
                    number_format($availableCash, 2, ',', '.'),
                    number_format($amount, 2, ',', '.')
                ));
            }

            // Gera protocolo único de custódia
            $prefix = match ($type) {
                CashTransferType::BLEED_TO_CENTRAL => 'SG',
                CashTransferType::SUPPLY_FROM_CENTRAL => 'SUP',
                CashTransferType::P2P_USER => 'P2P',
                CashTransferType::BANK_DEPOSIT => 'DEP',
            };
            $protocol = sprintf('%s-%s-%s', $prefix, date('Ymd'), strtoupper(Str::random(5)));

            $transfer = CashTransfer::create([
                'protocol_number' => $protocol,
                'source_session_id' => $sourceSession->id,
                'destination_session_id' => $destinationSession->id,
                'transfer_type' => $type,
                'amount' => $amount,
                'status' => CashTransferStatus::PENDING,
                'requested_by' => $userId ?: $sourceSession->user_id,
                'requested_at' => now(),
                'notes' => $notes,
            ]);

            return $transfer->load(['sourceSession.user', 'sourceSession.cashRegister', 'destinationSession.cashRegister']);
        });
    }

    /**
     * O destinatário (ex: ADM Central) confere os valores físicos e dá o Aceite formal.
     * Neste momento, os lançamentos contábeis de saída e entrada são concretizados.
     */
    public function acceptTransfer(CashTransfer $transfer, int $userId, ?string $notes = null): CashTransfer
    {
        return DB::transaction(function () use ($transfer, $userId, $notes) {
            $transfer = CashTransfer::lockForUpdate()->findOrFail($transfer->id);

            if (!$transfer->isPending()) {
                throw new LogicException("Esta transferência já foi processada anteriormente com status: {$transfer->status->value}.");
            }

            $source = $transfer->sourceSession()->lockForUpdate()->first();
            $destination = $transfer->destinationSession()->lockForUpdate()->first();

            // 1. Registra saída no caixa de origem
            $sourceCategory = match ($transfer->transfer_type) {
                CashTransferType::BLEED_TO_CENTRAL => CashMovementCategory::BLEED_SANGRIA,
                default => CashMovementCategory::TRANSFER_OUT,
            };

            CashMovement::create([
                'cash_session_id' => $source->id,
                'type' => CashMovementType::OUTFLOW,
                'category' => $sourceCategory,
                'amount' => $transfer->amount,
                'description' => sprintf(
                    'Transferência enviada [%s] para %s',
                    $transfer->protocol_number,
                    $destination->cashRegister->name
                ),
                'receipt_number' => $transfer->protocol_number,
                'created_by' => $userId,
            ]);

            // 2. Registra entrada no caixa de destino
            $destCategory = match ($transfer->transfer_type) {
                CashTransferType::SUPPLY_FROM_CENTRAL => CashMovementCategory::CHANGE_SUPPLY,
                default => CashMovementCategory::TRANSFER_IN,
            };

            CashMovement::create([
                'cash_session_id' => $destination->id,
                'type' => CashMovementType::INFLOW,
                'category' => $destCategory,
                'amount' => $transfer->amount,
                'description' => sprintf(
                    'Transferência recebida [%s] de %s (Operador: %s)',
                    $transfer->protocol_number,
                    $source->cashRegister->name,
                    $source->user?->name ?? 'Sistema'
                ),
                'receipt_number' => $transfer->protocol_number,
                'created_by' => $userId,
            ]);

            // 3. Atualiza status da transferência
            $transfer->update([
                'status' => CashTransferStatus::ACCEPTED,
                'processed_by' => $userId,
                'processed_at' => now(),
                'notes' => $notes ? ($transfer->notes . " | Aceite: " . $notes) : $transfer->notes,
            ]);

            return $transfer->fresh(['sourceSession', 'destinationSession', 'processedByUser']);
        });
    }

    /**
     * O destinatário recusa a transferência (ex: valor não confere com o envelope físico).
     */
    public function rejectTransfer(CashTransfer $transfer, string $reason, int $userId): CashTransfer
    {
        return DB::transaction(function () use ($transfer, $reason, $userId) {
            $transfer = CashTransfer::lockForUpdate()->findOrFail($transfer->id);

            if (!$transfer->isPending()) {
                throw new LogicException('Apenas transferências pendentes podem ser rejeitadas.');
            }

            if (empty(trim($reason))) {
                throw new LogicException('É obrigatório informar o motivo da recusa/contestação.');
            }

            $transfer->update([
                'status' => CashTransferStatus::REJECTED,
                'rejection_reason' => $reason,
                'processed_by' => $userId,
                'processed_at' => now(),
            ]);

            return $transfer->fresh();
        });
    }

    /**
     * Lista transferências pendentes direcionadas a uma sessão (ex: Caixa Central).
     */
    public function getPendingIncomingTransfers(string $destinationSessionId)
    {
        return CashTransfer::with(['sourceSession.cashRegister', 'sourceSession.user', 'requestedByUser'])
            ->where('destination_session_id', $destinationSessionId)
            ->where('status', CashTransferStatus::PENDING->value)
            ->latest('requested_at')
            ->get();
    }
}
