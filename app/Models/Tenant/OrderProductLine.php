<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderProductLine extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'order_product_lines';

    protected $fillable = [
        'order_of_service_id',
        'product_id',
        'product_batch_id',
        'quantity',
        'unit_price',
        'cost_price',
        'total_price',
        'total_cost',
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
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function serials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductSerial::class, 'order_product_line_id');
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
