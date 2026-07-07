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
            $model->created_by = Auth::id();
        });

        static::updating(function (Model $model) {
            $model->updated_by = Auth::id();
        });

        static::deleting(function (Model $model) {
            if ($model->isSoftDeleting()) {
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
