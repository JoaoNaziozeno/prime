<?php

namespace App\Enums\Tenant;

enum CashRegisterType: string
{
    case CENTRAL = 'central';
    case USER_STATION = 'user_station';
    case MOBILE = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::CENTRAL => 'Caixa Central / Cofre',
            self::USER_STATION => 'Ponto de Atendimento / Guichê',
            self::MOBILE => 'Ponto Móvel / Pátio',
        };
    }
}
