<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Master\User;

class QaDefect extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';
    protected $table = 'qa_defects';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'qa_inspection_id',
        'description',
        'severity',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    const STATUS_OPEN = 'open';
    const STATUS_IN_REWORK = 'in_rework';
    const STATUS_RESOLVED = 'resolved';

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(QaInspection::class, 'qa_inspection_id');
    }

    public function getResolverAttribute()
    {
        return $this->resolved_by ? User::find($this->resolved_by) : null;
    }
}
