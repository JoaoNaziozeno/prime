<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyMetricsSnapshot extends Model
{
    use HasFactory;

    protected $connection = 'tenant';
    protected $table = 'daily_metrics_snapshots';

    protected $fillable = [
        'snapshot_date',
        'revenue',
        'cost',
        'margin',
        'orders_created',
        'orders_completed',
        'nps_average',
        'defects_count',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'revenue' => 'float',
        'cost' => 'float',
        'margin' => 'float',
        'nps_average' => 'float',
        'orders_created' => 'integer',
        'orders_completed' => 'integer',
        'defects_count' => 'integer',
    ];
}
