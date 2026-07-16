<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreventiveRule extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'vehicle_id',
        'name',
        'description',
        'interval_kms',
        'interval_days',
        'is_active',
    ];

    protected $casts = [
        'interval_kms' => 'integer',
        'interval_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }
}
