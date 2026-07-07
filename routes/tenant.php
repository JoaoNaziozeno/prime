<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResolveTenantBySlug;
use App\Http\Middleware\EnsureTenantIsActive;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

/**
 * Tenant Web Routes
 * Accessible via: empresa.prime-erp.local or /tenant/{slug}
 */
Route::middleware([
    'web',
    ResolveTenantBySlug::class,
    EnsureTenantIsActive::class,
])->group(function () {
    Route::get('/', function () {
        $tenant = tenancy()->tenant();
        return response()->json([
            'message' => 'Welcome to tenant application',
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->company->name ?? 'Unknown',
        ]);
    });
});

/**
 * Tenant API Routes
 * Accessible via: api.prime-erp.local/{slug} or /api/tenant/{slug}
 */
Route::prefix('api')->middleware([
    'api',
    ResolveTenantBySlug::class,
    EnsureTenantIsActive::class,
])->group(function () {
    // API routes will be added in subsequent phases
    Route::get('/status', function () {
        $tenant = tenancy()->tenant();
        return response()->json([
            'status' => 'ok',
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
        ]);
    });
});
