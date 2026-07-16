<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Master\User;

class QaInspection extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';
    protected $table = 'qa_inspections';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_of_service_id',
        'qa_template_id',
        'inspector_id',
        'status',
        'items_checked',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'items_checked' => 'array',
        'completed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PASSED = 'passed';
    const STATUS_FAILED = 'failed';

    public function orderOfService(): BelongsTo
    {
        return $this->belongsTo(OrderOfService::class, 'order_of_service_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QaTemplate::class, 'qa_template_id');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(QaDefect::class, 'qa_inspection_id');
    }

    public function getInspectorAttribute()
    {
        return User::find($this->inspector_id);
    }
}
