<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Master\User;

class MaintenanceLog extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    public $incrementing = false;
    protected $keyType = 'string';

    const TYPE_PREVENTIVE = 'preventive';
    const TYPE_CORRECTIVE = 'corrective';
    const TYPE_PREDICTIVE = 'predictive';

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'vehicle_id',
        'preventive_rule_id',
        'order_of_service_id',
        'title',
        'description',
        'type',
        'status',
        'scheduled_date',
        'completed_date',
        'odometer',
        'cost',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'odometer' => 'integer',
        'cost' => 'decimal:2',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function preventiveRule(): BelongsTo
    {
        return $this->belongsTo(PreventiveRule::class);
    }

    public function orderOfService(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
