<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResolveTenantBySlug;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Controllers\API\Tenant\OrderOfServiceController;
use App\Http\Controllers\API\Tenant\OrderItemController;
use App\Http\Controllers\API\Tenant\ProductController;
use App\Http\Controllers\API\Tenant\ServiceController;
use App\Http\Controllers\API\Tenant\CategoryController;
use App\Http\Controllers\API\Tenant\WarehouseLocationController;
use App\Http\Controllers\API\Tenant\InventoryLogController;

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
    Route::get('/tenant-welcome', function () {
        $tenant = tenant();
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
    // Order of Service Routes
    Route::apiResource('orders', OrderOfServiceController::class);
    Route::post('/orders/{order}/approve', [OrderOfServiceController::class, 'approve']);
    Route::post('/orders/{order}/start', [OrderOfServiceController::class, 'start']);
    Route::post('/orders/{order}/complete', [OrderOfServiceController::class, 'complete']);
    Route::post('/orders/{order}/cancel', [OrderOfServiceController::class, 'cancel']);
    Route::post('/orders/{order}/hold', [OrderOfServiceController::class, 'hold']);
    Route::post('/orders/{order}/resume', [OrderOfServiceController::class, 'resume']);
    Route::get('/orders/stats/summary', [OrderOfServiceController::class, 'stats']);

    // Order Item Routes
    Route::apiResource('orders.items', OrderItemController::class, [
        'parameters' => ['order' => 'order'],
    ]);
    Route::post('/items/{item}/start', [OrderItemController::class, 'start']);
    Route::post('/items/{item}/complete', [OrderItemController::class, 'complete']);
    Route::post('/items/{item}/cancel', [OrderItemController::class, 'cancel']);
    Route::post('/items/{item}/block', [OrderItemController::class, 'block']);
    Route::post('/items/{item}/unblock', [OrderItemController::class, 'unblock']);

    // Product Routes
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock']);
    Route::get('/products/category/{category}', [ProductController::class, 'byCategory']);
    Route::get('/products/status/low-stock', [ProductController::class, 'lowStock']);
    Route::get('/products/stats', [ProductController::class, 'stats']);

    // Service Routes
    Route::apiResource('services', ServiceController::class);
    Route::get('/services/category/{category}', [ServiceController::class, 'byCategory']);
    Route::get('/services/stats', [ServiceController::class, 'stats']);

    // Category Routes
    Route::get('/product-categories', [CategoryController::class, 'indexProductCategories']);
    Route::post('/product-categories', [CategoryController::class, 'storeProductCategory']);
    Route::put('/product-categories/{category}', [CategoryController::class, 'updateProductCategory']);
    Route::delete('/product-categories/{category}', [CategoryController::class, 'destroyProductCategory']);

    Route::get('/service-categories', [CategoryController::class, 'indexServiceCategories']);
    Route::post('/service-categories', [CategoryController::class, 'storeServiceCategory']);
    Route::put('/service-categories/{category}', [CategoryController::class, 'updateServiceCategory']);
    Route::delete('/service-categories/{category}', [CategoryController::class, 'destroyServiceCategory']);

    // Warehouse Locations & Inventory Logs
    Route::apiResource('warehouse-locations', WarehouseLocationController::class);
    Route::get('/inventory-logs', [InventoryLogController::class, 'index']);
    Route::get('/inventory-logs/{inventoryLog}', [InventoryLogController::class, 'show']);
    Route::post('/inventory-logs', [InventoryLogController::class, 'store']);

    // Status endpoint
    Route::get('/status', function () {
        $tenant = tenancy()->tenant();
        return response()->json([
            'status' => 'ok',
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
        ]);
    });
});
