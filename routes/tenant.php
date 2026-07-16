<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResolveTenantBySlug;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ConfigureTenantIntegrations;
use App\Http\Controllers\API\Tenant\OrderOfServiceController;
use App\Http\Controllers\API\Tenant\OrderItemController;
use App\Http\Controllers\API\Tenant\ProductController;
use App\Http\Controllers\API\Tenant\ServiceController;
use App\Http\Controllers\API\Tenant\CategoryController;
use App\Http\Controllers\API\Tenant\WarehouseLocationController;
use App\Http\Controllers\API\Tenant\InventoryLogController;
use App\Http\Controllers\API\Tenant\NotificationController;
use App\Http\Controllers\API\Master\TwoFactorAuthController;
use App\Http\Controllers\API\Tenant\RbacController;
use App\Http\Controllers\API\Tenant\AuditLogController;
use App\Http\Controllers\API\Tenant\OrderProductLineController;
use App\Http\Controllers\API\Tenant\OrderServiceLineController;
use App\Http\Controllers\API\Tenant\LeadController;
use App\Http\Controllers\API\Tenant\LeadActivityController;
use App\Http\Controllers\API\Tenant\DocumentController;
use App\Http\Controllers\API\Tenant\InvoiceController;
use App\Http\Controllers\API\Tenant\PaymentTermController;
use App\Http\Controllers\API\Tenant\PaymentMethodController;
use App\Http\Controllers\API\Tenant\PaymentController;
use App\Http\Controllers\API\Tenant\PaymentWebhookController;
use App\Http\Controllers\API\Tenant\ReportController;
use App\Http\Controllers\API\Tenant\ScheduleController;
use App\Http\Controllers\API\Tenant\SettingController;
use App\Http\Controllers\API\Tenant\SupplierController;
use App\Http\Controllers\API\Tenant\PurchaseOrderController;
use App\Http\Controllers\API\Tenant\PreventiveRuleController;
use App\Http\Controllers\API\Tenant\MaintenanceLogController;
use App\Http\Controllers\API\Tenant\CustomerAuthController;
use App\Http\Controllers\API\Tenant\CustomerPortalController;
use App\Http\Controllers\API\Tenant\StaffOrderMessageController;
use App\Http\Controllers\API\Tenant\QaTemplateController;
use App\Http\Controllers\API\Tenant\QaInspectionController;
use App\Http\Controllers\API\Tenant\QualityMetricsController;
use App\Http\Controllers\API\Tenant\MobileSyncController;
use App\Http\Controllers\API\Tenant\CustomReportController;
use App\Http\Controllers\API\Tenant\ScheduledReportController;
use App\Http\Controllers\API\Tenant\BiDashboardController;
use App\Http\Controllers\API\Tenant\ComplianceController;

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
    ConfigureTenantIntegrations::class,
    'throttle:60,1',
    \App\Http\Middleware\SecurityHeadersMiddleware::class,
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

    // Advanced Inventory Routes
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::post('/purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('/purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive']);

    // Notification Routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/preferences', [NotificationController::class, 'getPreferences']);
    Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

    // Two-Factor Authentication Routes
    Route::post('/user/two-factor-authentication', [TwoFactorAuthController::class, 'enable']);
    Route::post('/user/confirmed-two-factor-authentication', [TwoFactorAuthController::class, 'confirm']);
    Route::delete('/user/two-factor-authentication', [TwoFactorAuthController::class, 'disable']);

    // RBAC Routes
    Route::get('/rbac/roles', [RbacController::class, 'indexRoles']);
    Route::post('/rbac/roles', [RbacController::class, 'storeRole']);
    Route::post('/rbac/assign', [RbacController::class, 'assignRole']);
    Route::post('/rbac/revoke', [RbacController::class, 'revokeRole']);

    // Audit Logs Routes
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // Cost Allocation Routes
    Route::get('/orders/{order}/products', [OrderProductLineController::class, 'index']);
    Route::post('/orders/{order}/products', [OrderProductLineController::class, 'store']);
    Route::put('/orders/{order}/products/{product}', [OrderProductLineController::class, 'update']);
    Route::delete('/orders/{order}/products/{product}', [OrderProductLineController::class, 'destroy']);
    Route::get('/orders/{order}/services', [OrderServiceLineController::class, 'index']);
    Route::post('/orders/{order}/services', [OrderServiceLineController::class, 'store']);
    Route::put('/orders/{order}/services/{service}', [OrderServiceLineController::class, 'update']);
    Route::delete('/orders/{order}/services/{service}', [OrderServiceLineController::class, 'destroy']);
    Route::post('/orders/{order}/services/{service}/start', [OrderServiceLineController::class, 'start']);
    Route::post('/orders/{order}/services/{service}/complete', [OrderServiceLineController::class, 'complete']);
    Route::post('/orders/{order}/services/{service}/cancel', [OrderServiceLineController::class, 'cancel']);

    // CRM Routes
    Route::apiResource('leads', LeadController::class);
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert']);
    Route::apiResource('leads.activities', LeadActivityController::class)->parameters(['leads' => 'lead']);
    Route::post('/activities/{activity}/complete', [LeadActivityController::class, 'complete']);

    // Document Management Routes
    Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

    // Financial Management Routes
    Route::post('/orders/{order}/invoice', [InvoiceController::class, 'createFromOrder']);
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay']);
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    Route::apiResource('payment-terms', PaymentTermController::class);
    Route::apiResource('payment-methods', PaymentMethodController::class);

    // Payment Processing Routes
    Route::get('/invoices/{invoice}/payments', [PaymentController::class, 'index']);
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);
    Route::post('/payments/pix', [PaymentController::class, 'generatePix']);
    Route::post('/payments/card', [PaymentController::class, 'chargeCard']);
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);
    Route::post('/payments/webhook/{provider}', [PaymentWebhookController::class, 'handle']);

    // Reporting & Analytics Routes
    Route::get('/reports/revenue', [ReportController::class, 'revenue']);
    Route::get('/reports/orders', [ReportController::class, 'orders']);
    Route::get('/reports/inventory', [ReportController::class, 'inventory']);
    Route::get('/reports/customers', [ReportController::class, 'customers']);
    Route::get('/reports/drivers', [ReportController::class, 'drivers']);
    Route::get('/reports/export/orders', [ReportController::class, 'exportOrders']);

    // Scheduling Routes
    Route::apiResource('schedules', ScheduleController::class);
    Route::post('/schedules/{schedule}/cancel', [ScheduleController::class, 'cancel']);

    // Maintenance Tracking Routes
    Route::apiResource('preventive-rules', PreventiveRuleController::class);
    Route::apiResource('maintenance-logs', MaintenanceLogController::class);
    Route::post('/maintenance-logs/{maintenance_log}/complete', [MaintenanceLogController::class, 'complete']);
    Route::get('/vehicles/{vehicle}/maintenance-status', [MaintenanceLogController::class, 'vehicleStatus']);
    Route::get('/vehicles/{vehicle}/maintenance-cost-analysis', [MaintenanceLogController::class, 'costAnalysis']);

    // Quality Management Routes
    Route::apiResource('qa-templates', QaTemplateController::class);
    Route::apiResource('qa-inspections', QaInspectionController::class);
    Route::post('/qa-inspections/{qa_inspection}/items', [QaInspectionController::class, 'updateItems']);
    Route::post('/qa-inspections/{qa_inspection}/complete', [QaInspectionController::class, 'complete']);
    Route::post('/qa-inspections/{qa_inspection}/defects', [QaInspectionController::class, 'logDefect']);
    Route::post('/qa-defects/{defect}/resolve', [QaInspectionController::class, 'resolveDefect']);
    Route::post('/orders/{order}/warranty', [QualityMetricsController::class, 'issueWarranty']);
    Route::get('/quality/metrics', [QualityMetricsController::class, 'metrics']);

    // Advanced Reports & BI Routes
    Route::apiResource('custom-reports', CustomReportController::class);
    Route::get('/custom-reports/{custom_report}/execute', [CustomReportController::class, 'execute']);
    Route::get('/custom-reports/{custom_report}/export', [CustomReportController::class, 'export']);
    Route::post('/custom-reports/{custom_report}/queue', [CustomReportController::class, 'queue']);
    Route::get('/custom-reports/{custom_report}/queue/status', [CustomReportController::class, 'queueStatus']);
    Route::get('/custom-reports/{custom_report}/queue/download', [CustomReportController::class, 'downloadQueued']);
    Route::apiResource('scheduled-reports', ScheduledReportController::class);
    Route::get('/bi/dashboard', [BiDashboardController::class, 'dashboard']);
    Route::post('/bi/snapshots/trigger', [BiDashboardController::class, 'triggerSnapshot']);

    // Compliance & Audit Routes
    Route::post('/compliance/customers/{customer}/anonymize', [ComplianceController::class, 'anonymizeCustomer']);
    Route::post('/compliance/drivers/{driver}/anonymize', [ComplianceController::class, 'anonymizeDriver']);
    Route::post('/compliance/purge-logs', [ComplianceController::class, 'purgeLogs']);
    Route::post('/compliance/invoices/{invoice}/nfe', [ComplianceController::class, 'transmitNfe']);

    // Settings Routes
    Route::get('/settings', [SettingController::class, 'index']);
    Route::post('/settings', [SettingController::class, 'update']);
    Route::post('/settings/logo', [SettingController::class, 'uploadLogo']);
    Route::get('/settings/logo/view', [SettingController::class, 'getLogo']);

    // Customer Portal Public Routes
    Route::post('/customer/login', [CustomerAuthController::class, 'login'])->middleware('throttle:5,1,login');
    Route::post('/customer/authenticate', [CustomerAuthController::class, 'authenticate'])->middleware('throttle:5,1,login');

    // Customer Portal Protected Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/customer/profile', [CustomerPortalController::class, 'profile']);
        Route::get('/customer/orders', [CustomerPortalController::class, 'orders']);
        Route::get('/customer/orders/{order}', [CustomerPortalController::class, 'showOrder']);
        Route::get('/customer/orders/{order}/messages', [CustomerPortalController::class, 'messages']);
        Route::post('/customer/orders/{order}/messages', [CustomerPortalController::class, 'sendMessage']);
        Route::post('/customer/orders/{order}/feedback', [QualityMetricsController::class, 'registerFeedback']);

        // Mobile App Sync Routes
        Route::get('/mobile/sync', [MobileSyncController::class, 'syncPull']);
        Route::post('/mobile/sync', [MobileSyncController::class, 'syncPush']);
        Route::get('/mobile/orders', [MobileSyncController::class, 'orders']);

        // Staff Order Messages
        Route::get('/orders/{order}/messages', [StaffOrderMessageController::class, 'index']);
        Route::post('/orders/{order}/messages', [StaffOrderMessageController::class, 'store']);
    });

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
