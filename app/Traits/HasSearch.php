<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait HasSearch
 * Facilita busca em modelos
 */
trait HasSearch
{
    /**
     * Colunas pesquisáveis por padrão
     */
    protected array $searchable = [];

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term || empty($this->searchable)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            foreach ($this->searchable as $column) {
                $q->orWhere($column, 'LIKE', "%{$term}%");
            }
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (method_exists($this, 'scope' . ucfirst($key))) {
                $query->{lcfirst($key)}($value);
            }
        }

        return $query;
    }
}
