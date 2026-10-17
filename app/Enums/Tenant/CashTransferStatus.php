<?php

namespace App\Enums\Tenant;

enum CashTransferStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente de Aceite (Em Trânsito)',
            self::ACCEPTED => 'Confirmado / Aceito',
            self::REJECTED => 'Rejeitado / Divergência',
            self::CANCELLED => 'Cancelado pelo Solicitante',
        };
    }
}
