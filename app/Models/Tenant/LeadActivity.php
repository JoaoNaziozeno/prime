<?php

namespace App\Models\Tenant;

use App\Traits\Tenant\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    use HasFactory, Auditable;

    protected $connection = 'tenant';

    public const TYPE_CALL = 'call';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_EMAIL = 'email';
    public const TYPE_TASK = 'task';
    public const TYPE_NOTE = 'note';

    protected $fillable = [
        'lead_id',
        'type',
        'title',
        'description',
        'due_date',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'created_by' => 'integer',
    ];

    /**
     * Get the lead associated with this activity.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
