<?php

namespace App\Enums\Tenant;

enum CashTransferType: string
{
    case BLEED_TO_CENTRAL = 'bleed_to_central';
    case SUPPLY_FROM_CENTRAL = 'supply_from_central';
    case P2P_USER = 'p2p_user';
    case BANK_DEPOSIT = 'bank_deposit';

    public function label(): string
    {
        return match ($this) {
            self::BLEED_TO_CENTRAL => 'Sangria para o Caixa Central',
            self::SUPPLY_FROM_CENTRAL => 'Suprimento do Caixa Central',
            self::P2P_USER => 'Transferência entre Operadores (P2P)',
            self::BANK_DEPOSIT => 'Depósito Bancário (Cofre -> Banco)',
        };
    }
}
