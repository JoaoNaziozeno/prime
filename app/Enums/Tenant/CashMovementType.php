<?php

namespace App\Enums\Tenant;

enum CashMovementType: string
{
    case INFLOW = 'inflow';
    case OUTFLOW = 'outflow';

    public function label(): string
    {
        return match ($this) {
            self::INFLOW => 'Entrada / Crédito',
            self::OUTFLOW => 'Saída / Débito',
        };
    }
}
