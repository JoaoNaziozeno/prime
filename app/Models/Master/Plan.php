<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'description',
        'type',
        'price',
        'billing_cycle_days',
        'max_users',
        'max_branches',
        'max_storage_gb',
        'has_api_access',
        'has_support',
        'features',
        'is_active',
        'trial_days',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'has_api_access' => 'boolean',
        'has_support' => 'boolean',
        'features' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== CONSTANTS =====
    const TYPE_MONTHLY = 'monthly';
    const TYPE_YEARLY = 'yearly';
    const TYPE_CUSTOM = 'custom';

    // ===== RELATIONSHIPS =====

    /**
     * Assinaturas com este plano
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // ===== SCOPES =====

    /**
     * Apenas planos ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Apenas planos inativos
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Ordenar por preço
     */
    public function scopeOrderByPrice($query, string $direction = 'asc')
    {
        return $query->orderBy('price', $direction);
    }

    /**
     * Buscar por tipo
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Planos populares
     */
    public function scopePopular($query)
    {
        return $query->active()->orderBy('price', 'asc');
    }

    // ===== ACCESSORS =====

    /**
     * Preço formatado
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    /**
     * Tipo em português
     */
    public function getTypeNameAttribute(): string
    {
        return match($this->type) {
            self::TYPE_MONTHLY => 'Mensal',
            self::TYPE_YEARLY => 'Anual',
            self::TYPE_CUSTOM => 'Customizado',
            default => 'Desconhecido',
        };
    }

    // ===== METHODS =====

    /**
     * Verificar se plano está ativo
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Obter features como array
     */
    public function getFeatures(): array
    {
        return $this->features ?? [];
    }

    /**
     * Adicionar feature
     */
    public function addFeature(string $feature): void
    {
        $features = $this->getFeatures();
        if (!in_array($feature, $features)) {
            $features[] = $feature;
            $this->features = $features;
            $this->save();
        }
    }

    /**
     * Remover feature
     */
    public function removeFeature(string $feature): void
    {
        $features = $this->getFeatures();
        $features = array_filter($features, fn($f) => $f !== $feature);
        $this->features = array_values($features);
        $this->save();
    }

    /**
     * Contar assinaturas ativas
     */
    public function getActiveSubscriptionsCount(): int
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->count();
    }
}
