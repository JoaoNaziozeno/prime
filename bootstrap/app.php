<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tenant middleware
        $middleware->group('tenant', [
            'App\Http\Middleware\ResolveTenantBySlug',
            'App\Http\Middleware\EnsureTenantIsActive',
            Stancl\Tenancy\Middleware\InitializeTenancy::class,
        ]);

        // Tenant API middleware
        $middleware->group('tenant.api', [
            'App\Http\Middleware\ResolveTenantBySlug',
            'App\Http\Middleware\EnsureTenantIsActive',
            Stancl\Tenancy\Middleware\InitializeTenancy::class,
            'throttle:60,1',
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
