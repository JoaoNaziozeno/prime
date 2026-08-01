<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasAudit;
use App\Traits\Tenant\HasTenantRoles;

class User extends Authenticatable
{
    use HasFactory, SoftDeletes, HasAudit, HasApiTokens, Notifiable, HasTenantRoles;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====

    /**
     * Empresas que o usuário é proprietário
     */
    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    /**
     * Empresas associadas ao usuário (many-to-many)
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withTimestamps()
            ->withPivot('role');
    }

    /**
     * Logs de auditoria criados por este usuário
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // ===== SCOPES =====

    /**
     * Apenas usuários ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Apenas super admins
     */
    public function scopeSuperAdmin($query)
    {
        return $query->where('role', 'super_admin');
    }

    /**
     * Apenas admins
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Apenas usuários comuns
     */
    public function scopeRegularUsers($query)
    {
        return $query->where('role', 'user');
    }

    /**
     * Buscar por email
     */
    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    // ===== ACCESSORS =====

    /**
     * Primeira letra do nome (para avatar)
     */
    public function getInitialAttribute(): string
    {
        return strtoupper(substr($this->name, 0, 1));
    }

    /**
     * Obter a primeira empresa associada (para compatibilidade com login único)
     */
    public function getCompanyAttribute()
    {
        return $this->companies()->first();
    }

    // ===== MUTATORS =====

    /**
     * Email sempre em minúsculas
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }

    // ===== METHODS =====

    /**
     * Verificar se o usuário está ativo
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Verificar se é super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Verificar se é admin
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    /**
     * Atualizar último login
     */
    public function recordLogin(string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    /**
     * Override notifications relationship to support Tenant Database Notifications
     */
    public function notifications()
    {
        $model = function_exists('tenant') && tenant()
            ? \App\Models\Tenant\TenantDatabaseNotification::class
            : \Illuminate\Notifications\DatabaseNotification::class;

        return $this->morphMany($model, 'notifiable')->orderBy('created_at', 'desc');
    }

    /**
     * Route notifications for the SMS channel.
     */
    public function routeNotificationForSms()
    {
        return $this->phone;
    }

    /**
     * Ensure the model always queries the central connection name.
     */
    public function getConnectionName()
    {
        return config('tenancy.database.central_connection');
    }
}
