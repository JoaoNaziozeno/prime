<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasUUID
 * Adiciona UUID automático ao modelo
 */
trait HasUUID
{
    public static function bootHasUUID(): void
    {
        static::creating(function (Model $model) {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
