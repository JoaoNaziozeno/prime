<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'user_email',
        'user_name',
        'model_type',
        'model_id',
        'model_name',
        'action',
        'description',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
        'method',
        'endpoint',
        'status_code',
        'metadata',
        'source',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'changed_fields' => 'json',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Timestamps
    public $timestamps = true;
    const UPDATED_AT = null; // Audit logs are immutable

    // ===== CONSTANTS =====
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';
    const ACTION_RESTORED = 'restored';
    const ACTION_CUSTOM = 'custom';

    const SOURCE_WEB = 'web';
    const SOURCE_API = 'api';
    const SOURCE_CLI = 'cli';

    // ===== RELATIONSHIPS =====

    /**
     * Usuário que fez a ação
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ===== SCOPES =====

    /**
     * Apenas logs de criação
     */
    public function scopeCreations($query)
    {
        return $query->where('action', self::ACTION_CREATED);
    }

    /**
     * Apenas logs de atualização
     */
    public function scopeUpdates($query)
    {
        return $query->where('action', self::ACTION_UPDATED);
    }

    /**
     * Apenas logs de deleção
     */
    public function scopeDeletions($query)
    {
        return $query->where('action', self::ACTION_DELETED);
    }

    /**
     * Apenas logs de restauração
     */
    public function scopeRestorations($query)
    {
        return $query->where('action', self::ACTION_RESTORED);
    }

    /**
     * Buscar por modelo
     */
    public function scopeForModel($query, string $modelType, ?int $modelId = null)
    {
        $query->where('model_type', $modelType);

        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        return $query;
    }

    /**
     * Buscar por usuário
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Buscar por IP
     */
    public function scopeByIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Buscar por método HTTP
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    /**
     * Buscar por endpoint
     */
    public function scopeByEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', 'LIKE', "%{$endpoint}%");
    }

    /**
     * Buscar por origem (web, api, cli)
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Logs recentes (últimos dias)
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Buscar por período
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ===== ACCESSORS =====

    /**
     * Ação em português
     */
    public function getActionNameAttribute(): string
    {
        return match($this->action) {
            self::ACTION_CREATED => 'Criado',
            self::ACTION_UPDATED => 'Atualizado',
            self::ACTION_DELETED => 'Deletado',
            self::ACTION_RESTORED => 'Restaurado',
            self::ACTION_CUSTOM => 'Customizado',
            default => 'Desconhecido',
        };
    }

    /**
     * Origem em português
     */
    public function getSourceNameAttribute(): string
    {
        return match($this->source) {
            self::SOURCE_WEB => 'Web',
            self::SOURCE_API => 'API',
            self::SOURCE_CLI => 'CLI',
            default => 'Desconhecido',
        };
    }

    /**
     * Obter nome amigável do modelo
     */
    public function getModelDisplayNameAttribute(): string
    {
        $parts = explode('\\', $this->model_type);
        return end($parts);
    }

    /**
     * Resumo da mudança
     */
    public function getChangeSummaryAttribute(): string
    {
        if (!$this->changed_fields) {
            return 'Sem mudanças';
        }

        $fields = is_array($this->changed_fields) ? $this->changed_fields : [];
        return implode(', ', $fields);
    }

    // ===== METHODS =====

    /**
     * Obter mudança de um campo específico
     */
    public function getFieldChange(string $field): ?array
    {
        $oldValue = $this->old_values[$field] ?? null;
        $newValue = $this->new_values[$field] ?? null;

        return [
            'old' => $oldValue,
            'new' => $newValue,
        ];
    }

    /**
     * Verificar se campo foi mudado
     */
    public function wasFieldChanged(string $field): bool
    {
        return isset($this->changed_fields) && 
               is_array($this->changed_fields) && 
               in_array($field, $this->changed_fields);
    }

    /**
     * Criar log de auditoria manualmente
     */
    public static function log(
        string $action,
        string $modelType,
        ?int $modelId = null,
        array $oldValues = [],
        array $newValues = [],
        string $description = null,
        string $source = self::SOURCE_WEB,
    ): self {
        $user = auth()->user();

        $changedFields = [];
        if (!empty($oldValues) || !empty($newValues)) {
            $changedFields = array_unique(array_merge(
                array_keys($oldValues),
                array_keys($newValues)
            ));
        }

        return self::create([
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_name' => $user?->name,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => request()->method(),
            'endpoint' => request()->path(),
            'status_code' => null,
            'source' => $source,
        ]);
    }
}
