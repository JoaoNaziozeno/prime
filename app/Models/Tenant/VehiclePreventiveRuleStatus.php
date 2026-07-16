<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiclePreventiveRuleStatus extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';
    protected $table = 'vehicle_preventive_rule_status';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'vehicle_id',
        'preventive_rule_id',
        'last_performed_kms',
        'last_performed_date',
        'next_due_kms',
        'next_due_date',
    ];

    protected $casts = [
        'last_performed_kms' => 'integer',
        'last_performed_date' => 'date',
        'next_due_kms' => 'integer',
        'next_due_date' => 'date',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function preventiveRule(): BelongsTo
    {
        return $this->belongsTo(PreventiveRule::class);
    }
}
