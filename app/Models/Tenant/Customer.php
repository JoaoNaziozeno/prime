<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Master\User;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'cpf_cnpj',
        'type',
        'street',
        'number',
        'complement',
        'city',
        'state',
        'country',
        'zip_code',
        'status',
        'activated_at',
        'suspended_at',
        'metadata',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'metadata' => 'json',
    ];

    /**
     * Constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    const TYPE_INDIVIDUAL = 'individual';
    const TYPE_COMPANY = 'company';

    /**
     * Relationships
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

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

    public function scopeIndividuals($query)
    {
        return $query->where('type', self::TYPE_INDIVIDUAL);
    }

    public function scopeCompanies($query)
    {
        return $query->where('type', self::TYPE_COMPANY);
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    public function scopeByCpfCnpj($query, $cpfCnpj)
    {
        $clean = preg_replace('/\D/', '', $cpfCnpj);
        return $query->where('cpf_cnpj', $clean);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Accessors
     */
    public function getIsActiveAttribute()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getIsSuspendedAttribute()
    {
        return $this->status === self::STATUS_SUSPENDED;
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

    public function getTypeNameAttribute()
    {
        return match($this->type) {
            self::TYPE_INDIVIDUAL => 'Pessoa Física',
            self::TYPE_COMPANY => 'Pessoa Jurídica',
            default => 'Desconhecido',
        };
    }

    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->street,
            $this->number,
            $this->complement ? "({$this->complement})" : null,
            $this->city,
            $this->state,
        ]);

        return implode(' - ', $parts);
    }

    /**
     * Mutators
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = $value ? strtolower($value) : null;
    }

    public function setCpfCnpjAttribute($value)
    {
        // Remove non-digits
        $this->attributes['cpf_cnpj'] = $value ? preg_replace('/\D/', '', $value) : null;
    }

    /**
     * Register custom factory
     */
    protected static function newFactory()
    {
        return \Database\Factories\Tenant\CustomerFactory::new();
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
        $this->activated_at = now();
        $this->suspended_at = null;
        $this->save();
    }

    public function suspend(): void
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->suspended_at = now();
        $this->save();
    }

    public function deactivate(): void
    {
        $this->status = self::STATUS_INACTIVE;
        $this->save();
    }

    public function getMetadataValue($key, $default = null)
    {
        return data_get($this->metadata ?? [], $key, $default);
    }

    public function setMetadataValue($key, $value): void
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;
        $this->save();
    }
}
