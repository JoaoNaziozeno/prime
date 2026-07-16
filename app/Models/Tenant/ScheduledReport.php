<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Master\User;

class ScheduledReport extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';
    protected $table = 'scheduled_reports';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'custom_report_id',
        'user_id',
        'frequency',
        'email_recipient',
        'last_sent_at',
        'is_active',
    ];

    protected $casts = [
        'last_sent_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_MONTHLY = 'monthly';

    public function customReport(): BelongsTo
    {
        return $this->belongsTo(CustomReport::class, 'custom_report_id');
    }

    public function getUserAttribute()
    {
        return User::find($this->user_id);
    }
}
