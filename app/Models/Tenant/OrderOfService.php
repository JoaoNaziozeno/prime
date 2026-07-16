<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Tenant\QaDefect;

class OrderOfService extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'orders_of_service';

    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'branch_id',
        'created_by',
        'updated_by',
        'status',
        'priority',
        'description',
        'internal_notes',
        'start_date',
        'expected_end_date',
        'actual_end_date',
        'estimated_cost',
        'actual_cost',
        'approved_amount',
        'reference_number',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'expected_end_date' => 'datetime',
        'actual_end_date' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ON_HOLD = 'on_hold';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function productLines(): HasMany
    {
        return $this->hasMany(OrderProductLine::class, 'order_of_service_id');
    }

    public function serviceLines(): HasMany
    {
        return $this->hasMany(OrderServiceLine::class, 'order_of_service_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderMessage::class, 'order_of_service_id');
    }

    public function qaInspections(): HasMany
    {
        return $this->hasMany(QaInspection::class, 'order_of_service_id');
    }

    public function feedback(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderFeedback::class, 'order_of_service_id');
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class, 'order_of_service_id');
    }

    /**
     * Getters for cost allocation totals and margins
     */
    public function getTotalBilledPriceAttribute(): float
    {
        $productsSum = (float) $this->productLines()->sum('total_price');
        $servicesSum = (float) $this->serviceLines()->sum('total_price');
        return $productsSum + $servicesSum;
    }

    public function getTotalCostPriceAttribute(): float
    {
        $productsSum = (float) $this->productLines()->sum('total_cost');
        $servicesSum = (float) $this->serviceLines()->sum('total_cost');
        return $productsSum + $servicesSum;
    }

    public function getMarginAmountAttribute(): float
    {
        return $this->total_billed_price - $this->total_cost_price;
    }

    public function getMarginPercentageAttribute(): float
    {
        $billed = $this->total_billed_price;
        if ($billed == 0) {
            return 0.0;
        }
        return ($this->margin_amount / $billed) * 100;
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
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

    public function scopeOnHold($query)
    {
        return $query->where('status', self::STATUS_ON_HOLD);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_ON_HOLD,
        ]);
    }

    public function scopeByBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByVehicle($query, string $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    // Business Logic Methods

    /**
     * Aprovação da ordem
     */
    public function approve(string $userId, ?float $approvedAmount = null): bool
    {
        if (!$this->canApprove()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'updated_by' => $userId,
            'approved_amount' => $approvedAmount ?? $this->estimated_cost,
        ]);

        return true;
    }

    /**
     * Iniciar execução
     */
    public function start(string $userId): bool
    {
        if (!$this->canStart()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'start_date' => now(),
            'updated_by' => $userId,
        ]);

        return true;
    }

    /**
     * Completar ordem
     */
    public function complete(string $userId, ?float $actualCost = null): bool
    {
        if (!$this->canComplete()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'actual_end_date' => now(),
            'actual_cost' => $actualCost ?? $this->estimated_cost,
            'updated_by' => $userId,
        ]);

        return true;
    }

    /**
     * Cancelar ordem
     */
    public function cancel(string $userId): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'updated_by' => $userId,
        ]);

        return true;
    }

    /**
     * Colocar em espera
     */
    public function hold(string $userId): bool
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_ON_HOLD,
            'updated_by' => $userId,
        ]);

        return true;
    }

    /**
     * Retomar de espera
     */
    public function resume(string $userId): bool
    {
        if ($this->status !== self::STATUS_ON_HOLD) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'updated_by' => $userId,
        ]);

        return true;
    }

    // Status Checks
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
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

    public function isOnHold(): bool
    {
        return $this->status === self::STATUS_ON_HOLD;
    }

    // Transition Checks
    public function canApprove(): bool
    {
        return $this->isDraft() || $this->isPendingApproval();
    }

    public function canStart(): bool
    {
        return $this->isApproved();
    }

    public function canComplete(): bool
    {
        if (!$this->isInProgress()) {
            return false;
        }

        $hasUnresolvedDefects = QaDefect::whereHas('inspection', function ($query) {
            $query->where('order_of_service_id', $this->id);
        })->whereIn('status', [QaDefect::STATUS_OPEN, QaDefect::STATUS_IN_REWORK])->exists();

        if ($hasUnresolvedDefects) {
            return false;
        }

        return true;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_ON_HOLD,
        ]);
    }

    // Calculated Properties
    public function getTotalItemsCount(): int
    {
        return $this->items()->count();
    }

    public function getCompletedItemsCount(): int
    {
        return $this->items()->where('status', OrderItem::STATUS_COMPLETED)->count();
    }

    public function getProgressPercentage(): int
    {
        $total = $this->getTotalItemsCount();
        if ($total === 0) {
            return 0;
        }

        return (int) (($this->getCompletedItemsCount() / $total) * 100);
    }

    public function isExpired(): bool
    {
        if (!$this->expected_end_date || $this->isCompleted()) {
            return false;
        }

        return now()->isAfter($this->expected_end_date);
    }

    public function getDaysOverdue(): ?int
    {
        if (!$this->isExpired()) {
            return null;
        }

        return $this->expected_end_date->diffInDays(now());
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
