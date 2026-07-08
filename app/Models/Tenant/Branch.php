<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Master\User;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'street',
        'number',
        'complement',
        'city',
        'state',
        'country',
        'zip_code',
        'status',
    ];

    /**
     * Constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Relationships
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
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

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
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
     * Methods
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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

    public function getActiveVehiclesCount(): int
    {
        return $this->vehicles()->active()->count();
    }

    /**
     * Register custom factory
     */
    protected static function newFactory()
    {
        return \Database\Factories\Tenant\BranchFactory::new();
    }
}
