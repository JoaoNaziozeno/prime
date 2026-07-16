<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'payments';

    protected $fillable = [
        'invoice_id',
        'payment_method_id',
        'amount',
        'gateway',
        'gateway_transaction_id',
        'status',
        'paid_at',
        'error_message',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    // Status checks
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    // State Transitions
    public function complete(?string $transactionId = null): void
    {
        if ($this->isCompleted()) {
            return;
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'paid_at' => now(),
            'gateway_transaction_id' => $transactionId ?? $this->gateway_transaction_id,
        ]);
    }

    public function fail(string $errorMessage): void
    {
        if ($this->isCompleted() || $this->isRefunded()) {
            return;
        }

        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    public function refund(): void
    {
        if (!$this->isCompleted()) {
            throw new \LogicException('Somente pagamentos concluídos podem ser estornados.');
        }

        $this->update([
            'status' => self::STATUS_REFUNDED,
        ]);
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
