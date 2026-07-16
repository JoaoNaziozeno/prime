<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderServiceLine extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'order_service_lines';

    protected $fillable = [
        'order_of_service_id',
        'service_id',
        'assigned_to',
        'quantity',
        'unit_price',
        'cost_price',
        'total_price',
        'total_cost',
        'status',
        'started_at',
        'completed_at',
        'hours_spent',
        'notes',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'hours_spent' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
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

    public function canStart(): bool
    {
        return $this->isPending();
    }

    public function canComplete(): bool
    {
        return $this->isInProgress();
    }

    public function canCancel(): bool
    {
        return $this->isPending() || $this->isInProgress();
    }

    public function start(): bool
    {
        if (!$this->canStart()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        return true;
    }

    public function complete(?float $hoursSpent = null): bool
    {
        if (!$this->canComplete()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'hours_spent' => $hoursSpent ?? $this->quantity,
        ]);

        return true;
    }

    public function cancel(): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        return true;
    }

    public function getMarginAmount(): float
    {
        return (float) ($this->total_price - $this->total_cost);
    }

    public function getMarginPercentage(): float
    {
        if ($this->total_price == 0) {
            return 0.0;
        }
        return (($this->total_price - $this->total_cost) / $this->total_price) * 100;
    }

    public function setMetadataValue(string $key, mixed $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }
}
