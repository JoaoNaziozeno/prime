<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'plate',
        'model',
        'brand',
        'year',
        'type',
        'vin',
        'color',
        'capacity_tons',
        'renavam',
        'license_expiration',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'capacity_tons' => 'decimal:2',
        'license_expiration' => 'date',
    ];

    /**
     * Constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_MAINTENANCE = 'maintenance';

    const TYPE_TRUCK = 'truck';
    const TYPE_VAN = 'van';
    const TYPE_CAR = 'car';
    const TYPE_MOTORCYCLE = 'motorcycle';
    const TYPE_TRAILER = 'trailer';

    /**
     * Relationships
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function scopeMaintenance($query)
    {
        return $query->where('status', self::STATUS_MAINTENANCE);
    }

    public function scopeByPlate($query, $plate)
    {
        return $query->where('plate', strtoupper($plate));
    }

    public function scopeByBrand($query, $brand)
    {
        return $query->where('brand', $brand);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeWithExpiredLicense($query)
    {
        return $query->where('license_expiration', '<', now()->toDateString());
    }

    public function scopeWithValidLicense($query)
    {
        return $query->where('license_expiration', '>=', now()->toDateString())
            ->orWhereNull('license_expiration');
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
            self::STATUS_MAINTENANCE => 'Em Manutenção',
            default => 'Desconhecido',
        };
    }

    public function getTypeNameAttribute()
    {
        return match($this->type) {
            self::TYPE_TRUCK => 'Caminhão',
            self::TYPE_VAN => 'Van',
            self::TYPE_CAR => 'Carro',
            self::TYPE_MOTORCYCLE => 'Motocicleta',
            self::TYPE_TRAILER => 'Reboque',
            default => 'Desconhecido',
        };
    }

    public function getIsLicenseExpiredAttribute()
    {
        return $this->license_expiration && $this->license_expiration < now()->toDateString();
    }

    public function getIsLicenseExpiringAttribute()
    {
        return $this->license_expiration && $this->license_expiration <= now()->addDays(30)->toDateString()
            && $this->license_expiration > now()->toDateString();
    }

    public function getFullInfoAttribute()
    {
        return "{$this->brand} {$this->model} ({$this->plate})";
    }

    /**
     * Mutators
     */
    public function setPlateAttribute($value)
    {
        $this->attributes['plate'] = $value ? strtoupper($value) : null;
    }

    public function setVinAttribute($value)
    {
        $this->attributes['vin'] = $value ? strtoupper($value) : null;
    }

    /**
     * Methods
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->status = self::STATUS_INACTIVE;
        $this->save();
    }

    public function sendToMaintenance(): void
    {
        $this->status = self::STATUS_MAINTENANCE;
        $this->save();
    }

    public function isLicenseExpired(): bool
    {
        return $this->license_expiration && $this->license_expiration < now()->toDateString();
    }

    public function isLicenseExpiring(): bool
    {
        return $this->license_expiration && $this->license_expiration <= now()->addDays(30)->toDateString()
            && $this->license_expiration > now()->toDateString();
    }

    public function getDaysUntilLicenseExpiration(): ?int
    {
        if (!$this->license_expiration) {
            return null;
        }

        return now()->diffInDays($this->license_expiration, false);
    }

    public function canOperate(): bool
    {
        return $this->isActive() && (!$this->license_expiration || $this->license_expiration >= now()->toDateString());
    }
}
