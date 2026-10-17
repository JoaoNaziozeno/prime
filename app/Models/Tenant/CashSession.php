<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\CashMovementType;
use App\Enums\Tenant\CashSessionStatus;
use App\Enums\Tenant\CashTransferStatus;
use App\Models\Master\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashSession extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'cash_sessions';

    protected $fillable = [
        'cash_register_id',
        'user_id',
        'status',
        'opened_at',
        'closed_at',
        'opening_balance',
        'calculated_cash_balance',
        'reported_cash_balance',
        'cash_difference',
        'closing_notes',
        'closed_by',
        'verified_by',
        'metadata',
    ];

    protected $casts = [
        'status' => CashSessionStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_balance' => 'decimal:2',
        'calculated_cash_balance' => 'decimal:2',
        'reported_cash_balance' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(CashTransfer::class, 'source_session_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(CashTransfer::class, 'destination_session_id');
    }

    public function denominations(): HasMany
    {
        return $this->hasMany(CashDenomination::class);
    }

    // Status helpers
    public function isOpen(): bool
    {
        return $this->status === CashSessionStatus::OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === CashSessionStatus::CLOSED;
    }

    /**
     * Calcula o saldo atual em dinheiro físico disponível na gaveta.
     * Saldo = Fundo de Troco + Entradas em Dinheiro - Saídas em Dinheiro - Sangrias Aceitas/Pendentes + Suprimentos
     */
    public function getCurrentCashBalance(): float
    {
        $inflows = (float) $this->movements()
            ->where('type', CashMovementType::INFLOW->value)
            ->whereHas('paymentMethod', function ($q) {
                $q->where('code', 'cash');
            })
            ->sum('amount');

        // Outras entradas sem método explícito (ex: suprimento inicial)
        $inflowsNoMethod = (float) $this->movements()
            ->where('type', CashMovementType::INFLOW->value)
            ->whereNull('payment_method_id')
            ->sum('amount');

        $outflows = (float) $this->movements()
            ->where('type', CashMovementType::OUTFLOW->value)
            ->sum('amount');

        return (float) $this->opening_balance + $inflows + $inflowsNoMethod - $outflows;
    }

    /**
     * Verifica se a gaveta ultrapassou o teto de segurança física.
     */
    public function isOverLimit(): bool
    {
        $limit = $this->cashRegister?->max_cash_limit;
        if (!$limit || $limit <= 0) {
            return false;
        }

        return $this->getCurrentCashBalance() >= (float) $limit;
    }

    /**
     * Retorna a porcentagem atingida do teto de gaveta.
     */
    public function getCashLimitUsagePercent(): float
    {
        $limit = (float) ($this->cashRegister?->max_cash_limit ?? 0);
        if ($limit <= 0) {
            return 0.0;
        }

        $balance = $this->getCurrentCashBalance();
        return round(($balance / $limit) * 100, 1);
    }
}
