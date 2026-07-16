<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warranty extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';
    protected $table = 'warranties';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_of_service_id',
        'type',
        'duration_days',
        'start_date',
        'end_date',
        'terms',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    const TYPE_FULL = 'full';
    const TYPE_PARTS = 'parts';
    const TYPE_LABOR = 'labor';

    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CLAIMED = 'claimed';

    public function orderOfService(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }
}
