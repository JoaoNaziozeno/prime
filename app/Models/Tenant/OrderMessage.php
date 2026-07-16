<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Master\User;

class OrderMessage extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';
    protected $table = 'order_messages';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_of_service_id',
        'sender_type',
        'sender_id',
        'message',
    ];

    public function orderOfService(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }

    /**
     * Get the sender instance dynamically
     */
    public function getSenderAttribute()
    {
        if ($this->sender_type === 'user') {
            return User::find($this->sender_id);
        }

        if ($this->sender_type === 'customer') {
            return Customer::find($this->sender_id);
        }

        return null;
    }
}
