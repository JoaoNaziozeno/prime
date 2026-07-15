<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Trait HasAudit
 * Rastreia todas as mudanças em um modelo
 */
trait HasAudit
{
    public static function bootHasAudit(): void
    {
        static::creating(function (Model $model) {
            if (is_null($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function (Model $model) {
            if (is_null($model->updated_by)) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting() && is_null($model->deleted_by)) {
                $model->deleted_by = Auth::id();
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'updated_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'deleted_by');
    }
}
