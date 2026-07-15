<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAudit;

class Company extends Model
{
    use HasFactory, SoftDeletes, HasAudit;

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'cnpj',
        'website',
        'address',
        'city',
        'state',
        'country',
        'zip_code',
        'status',
        'owner_id',
        'activated_at',
        'suspended_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== CONSTANTS =====
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    // ===== RELATIONSHIPS =====

    /**
     * Proprietário da empresa
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Usuários da empresa
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withTimestamps()
            ->withPivot('role');
    }

    /**
     * Plano de assinatura
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Assinatura ativa
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active');
    }

    /**
     * Tenants da empresa
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Tenant ativo (geralmente há apenas um)
     */
    public function activeTenant(): HasOne
    {
        return $this->hasOne(Tenant::class)
            ->where('status', 'active');
    }

    /**
     * Criador da empresa
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

    /**
     * Quem deletou (se soft deleted)
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ===== SCOPES =====

    /**
     * Apenas empresas ativas
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Apenas empresas suspensas
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    /**
     * Apenas empresas inativas
     */
    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Buscar por CNPJ
     */
    public function scopeByCnpj($query, string $cnpj)
    {
        return $query->where('cnpj', $cnpj);
    }

    /**
     * Buscar por proprietário
     */
    public function scopeByOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Recentes primeiro
     */
    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    // ===== ACCESSORS =====

    /**
     * Nome completo da empresa com status
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->status})";
    }

    /**
     * Verificar se está ativa
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // ===== MUTATORS =====

    /**
     * Email sempre em minúsculas
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * CNPJ sem formatação
     */
    public function setCnpjAttribute($value)
    {
        $this->attributes['cnpj'] = preg_replace('/[^0-9]/', '', $value);
    }

    // ===== METHODS =====

    /**
     * Verificar se empresa está ativa
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Verificar se empresa está suspensa
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Ativar empresa
     */
    public function activate(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
            'activated_at' => now(),
            'suspended_at' => null,
        ]);
    }

    /**
     * Suspender empresa
     */
    public function suspend(string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);
    }

    /**
     * Obter tenant ativo
     */
    public function getActiveTenant(): ?Tenant
    {
        return $this->activeTenant()->first();
    }

    /**
     * Obter assinatura ativa
     */
    public function getActiveSubscription(): ?Subscription
    {
        return $this->activeSubscription()->first();
    }
}
