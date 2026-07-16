<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Master\User;

class CustomReport extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'tenant';
    protected $table = 'custom_reports';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'model_type',
        'columns',
        'filters',
        'group_by',
        'created_by',
    ];

    protected $casts = [
        'columns' => 'array',
        'filters' => 'array',
    ];

    const TYPE_ORDERS = 'orders';
    const TYPE_FINANCIAL = 'financial';
    const TYPE_INVENTORY = 'inventory';
    const TYPE_MAINTENANCE = 'maintenance';

    public function scheduledReports(): HasMany
    {
        return $this->hasMany(ScheduledReport::class, 'custom_report_id');
    }

    public function getCreatorAttribute()
    {
        return User::find($this->created_by);
    }
}
