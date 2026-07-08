<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'cpf',
        'cnh',
        'cnh_category',
        'cnh_expiration',
        'status',
        'hired_at',
    ];

    protected $casts = [
        'cnh_expiration' => 'date',
        'hired_at' => 'datetime',
    ];

    /**
     * Constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    const CNH_CATEGORY_A = 'A';
    const CNH_CATEGORY_B = 'B';
    const CNH_CATEGORY_C = 'C';
    const CNH_CATEGORY_D = 'D';
    const CNH_CATEGORY_E = 'E';

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    public function scopeByCpf($query, $cpf)
    {
        $clean = preg_replace('/\D/', '', $cpf);
        return $query->where('cpf', $clean);
    }

    public function scopeByCnh($query, $cnh)
    {
        $clean = preg_replace('/\D/', '', $cnh);
        return $query->where('cnh', $clean);
    }

    public function scopeWithExpiredCnh($query)
    {
        return $query->where('cnh_expiration', '<', now()->toDateString());
    }

    public function scopeWithValidCnh($query)
    {
        return $query->where('cnh_expiration', '>=', now()->toDateString());
    }

    /**
     * Accessors
     */
    public function getIsActiveAttribute()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getStatusNameAttribute()
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'Ativo',
            self::STATUS_INACTIVE => 'Inativo',
            self::STATUS_SUSPENDED => 'Suspenso',
            default => 'Desconhecido',
        };
    }

    public function getCnhCategoryNameAttribute()
    {
        return match($this->cnh_category) {
            self::CNH_CATEGORY_A => 'Categoria A (Motocicletas)',
            self::CNH_CATEGORY_B => 'Categoria B (Carros)',
            self::CNH_CATEGORY_C => 'Categoria C (Caminhões)',
            self::CNH_CATEGORY_D => 'Categoria D (Ônibus)',
            self::CNH_CATEGORY_E => 'Categoria E (Reboque)',
            default => 'Desconhecida',
        };
    }

    public function getIsCnhExpiredAttribute()
    {
        return $this->cnh_expiration && $this->cnh_expiration < now()->toDateString();
    }

    public function getIsCnhExpiringAttribute()
    {
        return $this->cnh_expiration && $this->cnh_expiration <= now()->addDays(30)->toDateString()
            && $this->cnh_expiration > now()->toDateString();
    }

    /**
     * Mutators
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = $value ? strtolower($value) : null;
    }

    public function setCpfAttribute($value)
    {
        $this->attributes['cpf'] = $value ? preg_replace('/\D/', '', $value) : null;
    }

    public function setCnhAttribute($value)
    {
        $this->attributes['cnh'] = $value ? preg_replace('/\D/', '', $value) : null;
    }

    /**
     * Methods
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->save();
    }

    public function suspend(): void
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->status = self::STATUS_INACTIVE;
        $this->save();
    }

    public function isCnhExpired(): bool
    {
        return $this->cnh_expiration && $this->cnh_expiration < now()->toDateString();
    }

    public function isCnhExpiring(): bool
    {
        return $this->cnh_expiration && $this->cnh_expiration <= now()->addDays(30)->toDateString()
            && $this->cnh_expiration > now()->toDateString();
    }

    public function getDaysUntilCnhExpiration(): ?int
    {
        if (!$this->cnh_expiration) {
            return null;
        }

        return now()->diffInDays($this->cnh_expiration, false);
    }

    public function canOperate(): bool
    {
        return $this->isActive() && $this->cnh_expiration >= now()->toDateString();
    }

    /**
     * Register custom factory
     */
    protected static function newFactory()
    {
        return \Database\Factories\Tenant\DriverFactory::new();
    }
}
