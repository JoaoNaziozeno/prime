<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDenomination extends Model
{
    use HasUuids, HasFactory;

    protected $connection = 'tenant';

    protected $table = 'cash_denominations';

    public $timestamps = true;

    protected $fillable = [
        'cash_session_id',
        'count_type',
        'denomination',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'denomination' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }
}
