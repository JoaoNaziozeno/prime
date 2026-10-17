<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\CashMovementCategory;
use App\Enums\Tenant\CashMovementType;
use App\Models\Master\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashMovement extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'cash_movements';

    protected $fillable = [
        'cash_session_id',
        'order_of_service_id',
        'invoice_id',
        'payment_method_id',
        'type',
        'category',
        'amount',
        'description',
        'receipt_number',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'type' => CashMovementType::class,
        'category' => CashMovementCategory::class,
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function orderOfService(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
