<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAudit;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasInternalKeys;

class Tenant extends Model implements TenantWithDatabase
{
    use HasFactory, SoftDeletes, HasUuids, HasAudit, HasDatabase, HasInternalKeys;

    protected $table = 'tenants';

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection');
    }

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getTenantKey()
    {
        return $this->id;
    }

    public function getInternal(string $key)
    {
        if (app()->environment('testing') && $key === 'db_name') {
            return 'tenant_testing.sqlite';
        }

        $realKey = match ($key) {
            'db_name' => 'database_name',
            'db_host' => 'db_host',
            'db_username' => 'db_username',
            'db_password' => 'db_password',
            'db_port' => 'db_port',
            'db_driver' => 'db_driver',
            default => 'tenancy_' . $key,
        };

        return $this->getAttribute($realKey);
    }

    public function setInternal(string $key, $value)
    {
        $realKey = match ($key) {
            'db_name' => 'database_name',
            'db_host' => 'db_host',
            'db_username' => 'db_username',
            'db_password' => 'db_password',
            'db_port' => 'db_port',
            'db_driver' => 'db_driver',
            default => 'tenancy_' . $key,
        };

        $this->setAttribute($realKey, $value);
        return $this;
    }

    public function run(callable $callback)
    {
        $originalTenant = tenant();

        tenancy()->initialize($this);
        $result = $callback($this);

        if ($originalTenant) {
            tenancy()->initialize($originalTenant);
        } else {
            tenancy()->end();
        }

        return $result;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug) && $tenant->company) {
                $tenant->slug = self::generateSlug($tenant->company);
            }
        });
    }

    protected $fillable = [
        'company_id',
        'slug',
        'database_name',
        'hostname',
        'db_host',
        'db_port',
        'db_username',
        'db_password',
        'db_driver',
        'status',
        'activated_at',
        'settings',
        'notes',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'settings' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== CONSTANTS =====
    const STATUS_SETUP = 'setup';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_DELETED = 'deleted';

    const DRIVER_MYSQL = 'mysql';
    const DRIVER_PGSQL = 'pgsql';
    const DRIVER_SQLITE = 'sqlite';

    // ===== RELATIONSHIPS =====

    /**
     * Empresa do tenant
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Criador do tenant
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
     * Apenas tenants ativos
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Apenas tenants em setup
     */
    public function scopeInSetup($query)
    {
        return $query->where('status', self::STATUS_SETUP);
    }

    /**
     * Apenas tenants pausados
     */
    public function scopePaused($query)
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    /**
     * Apenas tenants deletados
     */
    public function scopeDeleted($query)
    {
        return $query->where('status', self::STATUS_DELETED);
    }

    /**
     * Buscar por slug
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Buscar por hostname
     */
    public function scopeByHostname($query, string $hostname)
    {
        return $query->where('hostname', $hostname);
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
     * Verificar se está ativo
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Verificar se está em setup
     */
    public function getIsInSetupAttribute(): bool
    {
        return $this->status === self::STATUS_SETUP;
    }

    /**
     * Status em português
     */
    public function getStatusNameAttribute(): string
    {
        return match($this->status) {
            self::STATUS_SETUP => 'Em Setup',
            self::STATUS_ACTIVE => 'Ativo',
            self::STATUS_PAUSED => 'Pausado',
            self::STATUS_DELETED => 'Deletado',
            default => 'Desconhecido',
        };
    }

    /**
     * Driver em português
     */
    public function getDriverNameAttribute(): string
    {
        return match($this->db_driver) {
            self::DRIVER_MYSQL => 'MySQL',
            self::DRIVER_PGSQL => 'PostgreSQL',
            self::DRIVER_SQLITE => 'SQLite',
            default => 'Desconhecido',
        };
    }

    // ===== METHODS =====

    /**
     * Ativar tenant
     */
    public function activate(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
    }

    /**
     * Pausar tenant
     */
    public function pause(): bool
    {
        return $this->update([
            'status' => self::STATUS_PAUSED,
        ]);
    }

    /**
     * Deletar tenant
     */
    public function markAsDeleted(): bool
    {
        return $this->update([
            'status' => self::STATUS_DELETED,
        ]);
    }

    /**
     * Obter configurações
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?? [];
        }
        return $settings[$key] ?? $default;
    }

    /**
     * Atualizar configuração
     */
    public function setSetting(string $key, $value): self
    {
        $settings = $this->settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?? [];
        }
        $settings[$key] = $value;
        $this->settings = $settings;
        return $this;
    }

    /**
     * Obter string de conexão
     */
    public function getConnectionString(): string
    {
        return "{$this->db_driver}://{$this->db_username}@{$this->db_host}:{$this->db_port}/{$this->database_name}";
    }

    /**
     * Gerar conexão para database tenant
     */
    public function getConnectionConfig(): array
    {
        return [
            'driver' => $this->db_driver,
            'host' => $this->db_host,
            'port' => $this->db_port,
            'database' => $this->database_name,
            'username' => $this->db_username,
            'password' => $this->db_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ];
    }

    /**
     * Gerar slug baseado no nome da empresa
     */
    public static function generateSlug(Company $company): string
    {
        $slug = \Illuminate\Support\Str::slug($company->name);
        $exists = true;
        $count = 1;

        while ($exists) {
            $newSlug = $slug . ($count > 1 ? "-{$count}" : '');
            $exists = self::where('slug', $newSlug)->exists();
            $count++;
        }

        return $newSlug;
    }
}
