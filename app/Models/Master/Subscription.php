<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAudit;

class Subscription extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'subscriptions';

    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'started_at',
        'renews_at',
        'cancelled_at',
        'trial_ends_at',
        'current_amount',
        'payment_method',
        'payment_reference',
        'payment_retries',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'renews_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_amount' => 'decimal:2',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== CONSTANTS =====
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    const PAYMENT_CREDIT_CARD = 'credit_card';
    const PAYMENT_BOLETO = 'boleto';
    const PAYMENT_PIX = 'pix';
    const PAYMENT_BANK_TRANSFER = 'bank_transfer';

    // ===== RELATIONSHIPS =====

    /**
     * Empresa da assinatura
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Plano da assinatura
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Criador da assinatura
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Quem atualizou por último
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ===== SCOPES =====

    /**
     * Apenas assinaturas ativas
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Apenas assinaturas pausadas
     */
    public function scopePaused($query)
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    /**
     * Apenas assinaturas canceladas
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Apenas assinaturas expiradas
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Que vencem em breve (próximos 7 dias)
     */
    public function scopeRenewingsoon($query)
    {
        $sevenDaysFromNow = now()->addDays(7);
        return $query->active()
            ->whereBetween('renews_at', [now(), $sevenDaysFromNow]);
    }

    /**
     * Que venceram
     */
    public function scopeOverdue($query)
    {
        return $query->where('renews_at', '<', now());
    }

    /**
     * Em período de trial
     */
    public function scopeOnTrial($query)
    {
        return $query->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now());
    }

    /**
     * Buscar por empresa
     */
    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ===== ACCESSORS =====

    /**
     * Verificar se em período de trial
     */
    public function getIsOnTrialAttribute(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Dias até renovação
     */
    public function getDaysUntilRenewalAttribute(): ?int
    {
        if (!$this->renews_at) {
            return null;
        }
        return (int) $this->renews_at->diffInDays(now());
    }

    /**
     * Montante formatado
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->current_amount, 2, ',', '.');
    }

    /**
     * Status em português
     */
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'Ativa',
            self::STATUS_PAUSED => 'Pausada',
            self::STATUS_CANCELLED => 'Cancelada',
            self::STATUS_EXPIRED => 'Expirada',
            default => 'Desconhecido',
        };
    }

    // ===== METHODS =====

    /**
     * Verificar se assinatura está ativa
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Ativar assinatura
     */
    public function activate(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
            'started_at' => now(),
            'cancelled_at' => null,
        ]);
    }

    /**
     * Pausar assinatura
     */
    public function pause(): bool
    {
        return $this->update([
            'status' => self::STATUS_PAUSED,
        ]);
    }

    /**
     * Cancelar assinatura
     */
    public function cancel(): bool
    {
        return $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Renovar assinatura
     */
    public function renew(): bool
    {
        $renewalDate = $this->renews_at->addDays($this->plan->billing_cycle_days);

        return $this->update([
            'renews_at' => $renewalDate,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Verificar se vencimento está próximo
     */
    public function isRenewingSoon(): bool
    {
        return $this->renews_at && $this->renews_at->diffInDays(now()) <= 7;
    }

    /**
     * Verificar se está vencida
     */
    public function isOverdue(): bool
    {
        return $this->renews_at && $this->renews_at->isPast();
    }

    /**
     * Obter dias até vencimento
     */
    public function getDaysUntilExpiration(): int
    {
        return max(0, (int) $this->renews_at->diffInDays(now(), false));
    }
}
