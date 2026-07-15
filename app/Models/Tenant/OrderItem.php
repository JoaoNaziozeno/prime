<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'order_items';

    protected $fillable = [
        'order_of_service_id',
        'assigned_to',
        'created_by',
        'description',
        'notes',
        'sequence',
        'quantity',
        'unit_price',
        'estimated_cost',
        'actual_cost',
        'status',
        'started_at',
        'completed_at',
        'hours_spent',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'hours_spent' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_BLOCKED = 'blocked';

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', self::STATUS_BLOCKED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_BLOCKED,
        ]);
    }

    public function scopeByOrder($query, string $orderId)
    {
        return $query->where('order_of_service_id', $orderId);
    }

    public function scopeBySequence($query)
    {
        return $query->orderBy('sequence');
    }

    // Business Logic Methods

    /**
     * Iniciar execução do item
     */
    public function start(string $userId): bool
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

    /**
     * Completar item
     */
    public function complete(string $userId, ?float $actualCost = null, ?float $hoursSpent = null): bool
    {
        if (!$this->canComplete()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'actual_cost' => $actualCost ?? $this->estimated_cost,
            'hours_spent' => $hoursSpent,
        ]);

        return true;
    }

    /**
     * Cancelar item
     */
    public function cancel(string $userId): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        return true;
    }

    /**
     * Bloquear item (depende de outro item)
     */
    public function block(string $userId): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_BLOCKED,
        ]);

        return true;
    }

    /**
     * Desbloquear item
     */
    public function unblock(string $userId): bool
    {
        if ($this->status !== self::STATUS_BLOCKED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_PENDING,
        ]);

        return true;
    }

    // Status Checks
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

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    // Transition Checks
    public function canStart(): bool
    {
        return $this->isPending() || $this->isBlocked();
    }

    public function canComplete(): bool
    {
        return $this->isInProgress();
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_BLOCKED,
        ]);
    }

    // Calculated Properties
    public function getElapsedTime(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffInMinutes($endTime);
    }

    public function getCostVariance(): ?float
    {
        if (!$this->actual_cost) {
            return null;
        }

        return $this->actual_cost - $this->estimated_cost;
    }

    public function getCostVariancePercentage(): ?float
    {
        if ($this->estimated_cost == 0 || !$this->actual_cost) {
            return null;
        }

        return (($this->actual_cost - $this->estimated_cost) / $this->estimated_cost) * 100;
    }

    // Meta accessors
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
};
