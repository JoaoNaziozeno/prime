<?php

namespace App\Enums\Tenant;

enum CashMovementCategory: string
{
    case OS_PAYMENT = 'os_payment';
    case OS_ADVANCE = 'os_advance';
    case CHANGE_SUPPLY = 'change_supply';
    case BLEED_SANGRIA = 'bleed_sangria';
    case PETTY_EXPENSE = 'petty_expense';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case DISCREPANCY_ADJUSTMENT = 'discrepancy_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::OS_PAYMENT => 'Recebimento de O.S',
            self::OS_ADVANCE => 'Adiantamento / Sinal de O.S',
            self::CHANGE_SUPPLY => 'Suprimento / Fundo de Troco',
            self::BLEED_SANGRIA => 'Sangria de Caixa',
            self::PETTY_EXPENSE => 'Despesa Miúda de Balcão',
            self::TRANSFER_IN => 'Transferência Recebida',
            self::TRANSFER_OUT => 'Transferência Enviada',
            self::DISCREPANCY_ADJUSTMENT => 'Ajuste de Divergência',
        };
    }
}
