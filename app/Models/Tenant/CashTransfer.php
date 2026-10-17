<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\CashTransferStatus;
use App\Enums\Tenant\CashTransferType;
use App\Models\Master\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashTransfer extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'cash_transfers';

    protected $fillable = [
        'protocol_number',
        'source_session_id',
        'destination_session_id',
        'transfer_type',
        'amount',
        'status',
        'requested_by',
        'processed_by',
        'requested_at',
        'processed_at',
        'notes',
        'rejection_reason',
        'receipt_url',
        'metadata',
    ];

    protected $casts = [
        'transfer_type' => CashTransferType::class,
        'status' => CashTransferStatus::class,
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function sourceSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'source_session_id');
    }

    public function destinationSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'destination_session_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status === CashTransferStatus::PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === CashTransferStatus::ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === CashTransferStatus::REJECTED;
    }
}
