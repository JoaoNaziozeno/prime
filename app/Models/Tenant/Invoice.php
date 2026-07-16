<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Tenant\Auditable;

class Invoice extends Model
{
    use HasUuids, HasFactory, SoftDeletes, Auditable;

    protected $connection = 'tenant';

    protected $table = 'invoices';

    protected $fillable = [
        'order_of_service_id',
        'customer_id',
        'branch_id',
        'payment_term_id',
        'payment_method_id',
        'invoice_number',
        'status',
        'issue_date',
        'due_date',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'notes',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'branch_id' => 'integer',
        'issue_date' => 'datetime',
        'due_date' => 'datetime',
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'metadata' => 'array',
        'notes' => 'encrypted',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    // Status Checks
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_PAID;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // State Transitions Validation
    public function canSend(): bool
    {
        return $this->isDraft();
    }

    public function canRecordPayment(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_PARTIALLY_PAID]);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_PARTIALLY_PAID]);
    }

    // Metadata helpers
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
