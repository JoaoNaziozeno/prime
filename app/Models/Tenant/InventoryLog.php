<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasAudit;

class InventoryLog extends Model
{
    use HasUuids, SoftDeletes, HasFactory, HasAudit;

    protected $connection = 'tenant';
    protected $table = 'inventory_logs';

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'balance_after',
        'unit_cost',
        'unit_price',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_after' => 'integer',
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    // Constantes de tipos de movimentação
    public const TYPE_INBOUND = 'inbound';
    public const TYPE_OUTBOUND = 'outbound';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER = 'transfer';

    /**
     * Relacionamento com produto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
