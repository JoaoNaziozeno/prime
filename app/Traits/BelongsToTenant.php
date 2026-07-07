<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Trait BelongsToTenant
 * Automáticamente filtra dados por tenant
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = Tenancy::current()?->id;
            
            if ($tenantId) {
                $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function ($model) {
            if (! isset($model->tenant_id)) {
                $model->tenant_id = Tenancy::current()?->id;
            }
        });
    }

    public function scopeForTenant(Builder $query, $tenantId): Builder
    {
        return $query->where($this->getTable().'.tenant_id', $tenantId);
    }
}
