<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasAudit;

class WarehouseLocation extends Model
{
    use HasUuids, SoftDeletes, HasFactory, HasAudit;

    protected $connection = 'tenant';
    protected $table = 'warehouse_locations';

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * Relacionamento com produtos
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'warehouse_location_id');
    }
}
