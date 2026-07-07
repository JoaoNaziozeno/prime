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
use App\Policies\UserPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\PlanPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\TenantPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\BranchPolicy;
use App\Policies\DriverPolicy;
use App\Policies\VehiclePolicy;
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
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
