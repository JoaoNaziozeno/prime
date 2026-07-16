<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSerial extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';

    public $incrementing = false;
    protected $keyType = 'string';

    const STATUS_AVAILABLE = 'available';
    const STATUS_RESERVED = 'reserved';
    const STATUS_SOLD = 'sold';
    const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'product_id',
        'serial_number',
        'status',
        'purchase_order_item_id',
        'order_product_line_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function orderProductLine(): BelongsTo
    {
        return $this->belongsTo(OrderProductLine::class);
    }
}
