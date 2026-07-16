<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Master\User;

class PurchaseOrder extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    public $incrementing = false;
    protected $keyType = 'string';

    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_APPROVED = 'approved';
    const STATUS_RECEIVED = 'received';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'supplier_id',
        'branch_id',
        'status',
        'total_amount',
        'notes',
        'ordered_at',
        'received_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
