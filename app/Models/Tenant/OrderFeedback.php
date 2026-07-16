<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFeedback extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';
    protected $table = 'order_feedback';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_of_service_id',
        'rating',
        'nps_score',
        'comments',
    ];

    public function orderOfService(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }
}
