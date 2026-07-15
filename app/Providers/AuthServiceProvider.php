<?php

namespace App\Providers;

use App\Models\Master\User;
use App\Models\Master\Company;
use App\Models\Master\Plan;
use App\Models\Master\Subscription;
use App\Models\Master\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\Service;
use App\Models\Tenant\ServiceCategory;
use App\Models\Tenant\WarehouseLocation;
use App\Models\Tenant\InventoryLog;
use App\Policies\UserPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\PlanPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\TenantPolicy;
use App\Policies\Tenant\CustomerPolicy;
use App\Policies\Tenant\BranchPolicy;
use App\Policies\Tenant\DriverPolicy;
use App\Policies\Tenant\VehiclePolicy;
use App\Policies\Tenant\OrderOfServicePolicy;
use App\Policies\Tenant\OrderItemPolicy;
use App\Policies\Tenant\ProductPolicy;
use App\Policies\Tenant\ProductCategoryPolicy;
use App\Policies\Tenant\ServicePolicy;
use App\Policies\Tenant\ServiceCategoryPolicy;
use App\Policies\Tenant\WarehouseLocationPolicy;
use App\Policies\Tenant\InventoryLogPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Master policies
        User::class => UserPolicy::class,
        Company::class => CompanyPolicy::class,
        Plan::class => PlanPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        Tenant::class => TenantPolicy::class,

        // Tenant policies
        Customer::class => CustomerPolicy::class,
        Branch::class => BranchPolicy::class,
        Driver::class => DriverPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        OrderOfService::class => OrderOfServicePolicy::class,
        OrderItem::class => OrderItemPolicy::class,
        Product::class => ProductPolicy::class,
        ProductCategory::class => ProductCategoryPolicy::class,
        Service::class => ServicePolicy::class,
        ServiceCategory::class => ServiceCategoryPolicy::class,
        WarehouseLocation::class => WarehouseLocationPolicy::class,
        InventoryLog::class => InventoryLogPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
