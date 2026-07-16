<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'schedules';

    protected $fillable = [
        'branch_id',
        'customer_id',
        'vehicle_id',
        'order_of_service_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'status',
        'assigned_to',
        'notes',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'customer_id' => 'integer',
        'vehicle_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }

    // Status checks
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isNoShow(): bool
    {
        return $this->status === self::STATUS_NO_SHOW;
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('start_time', '>=', now());
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByAssignedUser($query, string $userId)
    {
        return $query->where('assigned_to', $userId);
    }
}
