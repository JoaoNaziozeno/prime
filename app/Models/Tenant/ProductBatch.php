<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatch extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'batch_number',
        'expiration_date',
        'initial_quantity',
        'current_quantity',
        'purchase_order_item_id',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'initial_quantity' => 'decimal:2',
        'current_quantity' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * Check if the batch is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expiration_date) {
            return false;
        }

        return $this->expiration_date->isPast();
    }
}
