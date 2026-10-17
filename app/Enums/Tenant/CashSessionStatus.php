<?php

namespace App\Enums\Tenant;

enum CashSessionStatus: string
{
    case OPEN = 'open';
    case CLOSING = 'closing';
    case CLOSED = 'closed';
    case CONTESTED = 'contested';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberto',
            self::CLOSING => 'Em Fechamento',
            self::CLOSED => 'Fechado',
            self::CONTESTED => 'Com Divergência / Contestação',
        };
    }
}
