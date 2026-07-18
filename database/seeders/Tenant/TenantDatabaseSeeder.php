<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Vehicle;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create branches
        $branches = Branch::factory(2)->active()->create();
        Branch::factory(1)->inactive()->create();

        // Create customers
        Customer::factory(5)->active()->individual()->create();
        Customer::factory(3)->active()->company()->create();
        Customer::factory(1)->suspended()->create();
        Customer::factory(1)->inactive()->create();

        // Create drivers
        Driver::factory(3)->active()->categoryD()->create();
        Driver::factory(2)->active()->categoryB()->create();
        Driver::factory(1)->suspended()->create();
        Driver::factory(1)->inactive()->withExpiredCnh()->create();

        // Create vehicles
        foreach ($branches as $branch) {
            Vehicle::factory(2)->active()->truck()->forBranch($branch)->create();
            Vehicle::factory(1)->active()->van()->forBranch($branch)->create();
        }

        Vehicle::factory(1)->active()->car()->create();
        Vehicle::factory(1)->inactive()->create();
        Vehicle::factory(1)->maintenance()->create();
        Vehicle::factory(1)->withExpiredLicense()->create();

        $this->call([
            ProductAndServiceSeeder::class,
            OrderOfServiceSeeder::class,
        ]);
    }
}
