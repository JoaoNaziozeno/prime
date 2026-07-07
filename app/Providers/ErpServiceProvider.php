<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ErpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Default string length para MySQL
        Schema::defaultStringLength(191);

        // Registrar macros úteis
        $this->registerMacros();

        // Publicar configurações
        $this->publishConfig();
    }

    /**
     * Registrar macros úteis para queries
     */
    private function registerMacros(): void
    {
        Builder::macro('whereLike', function ($columns, $value) {
            return $this->where(function ($query) use ($columns, $value) {
                foreach ((array) $columns as $column) {
                    $query->orWhere($column, 'LIKE', "%{$value}%");
                }
            });
        });

        Builder::macro('orderBySearch', function ($columns, $value) {
            // Order by relevância na busca
            $cases = array_map(function ($column) use ($value) {
                return "WHEN {$column} LIKE ? THEN 1
                        WHEN {$column} LIKE ? THEN 2";
            }, (array) $columns);

            return $this->orderByRaw('CASE ' . implode(' ') . ' ELSE 3 END', 
                array_merge(...array_map(function ($col) use ($value) {
                    return ["{$value}", "%{$value}%"];
                }, (array) $columns)));
        });
    }

    /**
     * Publicar configurações
     */
    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/erp.php' => config_path('erp.php'),
        ], 'erp-config');
    }
}
