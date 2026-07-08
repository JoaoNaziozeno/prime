<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Branch;
use Illuminate\Database\Seeder;

class OrderOfServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing data or create
        $branches = Branch::all();
        if ($branches->isEmpty()) {
            $branches = Branch::factory(3)->create();
        }

        $customers = Customer::all();
        if ($customers->isEmpty()) {
            $customers = Customer::factory(10)->create();
        }

        $vehicles = Vehicle::all();
        if ($vehicles->isEmpty()) {
            $vehicles = Vehicle::factory(10)->create();
        }

        // Create sample orders with different statuses
        $this->createDraftOrders($branches, $customers, $vehicles);
        $this->createPendingApprovalOrders($branches, $customers, $vehicles);
        $this->createApprovedOrders($branches, $customers, $vehicles);
        $this->createInProgressOrders($branches, $customers, $vehicles);
        $this->createCompletedOrders($branches, $customers, $vehicles);
        $this->createCancelledOrders($branches, $customers, $vehicles);
    }

    private function createDraftOrders($branches, $customers, $vehicles): void
    {
        OrderOfService::factory(2)
            ->draft()
            ->sequence(fn () => [
                'branch_id' => $branches->random()->id,
                'customer_id' => $customers->random()->id,
                'vehicle_id' => $vehicles->random()->id,
            ])
            ->create()
            ->each(function ($order) {
                OrderItem::factory(2)->forOrder($order)->pending()->create();
            });
    }

    private function createPendingApprovalOrders($branches, $customers, $vehicles): void
    {
        OrderOfService::factory(2)
            ->pendingApproval()
            ->sequence(fn () => [
                'branch_id' => $branches->random()->id,
                'customer_id' => $customers->random()->id,
                'vehicle_id' => $vehicles->random()->id,
            ])
            ->create()
            ->each(function ($order) {
                OrderItem::factory(3)->forOrder($order)->pending()->create();
            });
    }

    private function createApprovedOrders($branches, $customers, $vehicles): void
    {
        OrderOfService::factory(3)
            ->approved()
            ->sequence(fn () => [
                'branch_id' => $branches->random()->id,
                'customer_id' => $customers->random()->id,
                'vehicle_id' => $vehicles->random()->id,
            ])
            ->create()
            ->each(function ($order) {
                OrderItem::factory(3)->forOrder($order)->pending()->create();
            });
    }

    private function createInProgressOrders($branches, $customers, $vehicles): void
    {
        OrderOfService::factory(3)
            ->inProgress()
            ->sequence(fn () => [
                'branch_id' => $branches->random()->id,
                'customer_id' => $customers->random()->id,
                'vehicle_id' => $vehicles->random()->id,
            ])
            ->create()
            ->each(function ($order) {
                // Mix of pending, in progress, and completed items
                OrderItem::factory(1)->forOrder($order)->pending()->create();
                OrderItem::factory(2)->forOrder($order)->inProgress()->create();
                OrderItem::factory(1)->forOrder($order)->completed()->create();
            });
    }

    private function createCompletedOrders($branches, $customers, $vehicles): void
    {
        OrderOfService::factory(5)
            ->completed()
            ->sequence(fn () => [
                'branch_id' => $branches->random()->id,
                'customer_id' => $customers->random()->id,
                'vehicle_id' => $vehicles->random()->id,
            ])
            ->create()
            ->each(function ($order) {
                OrderItem::factory(4)->forOrder($order)->completed()->create();
            });
    }

    private function createCancelledOrders($branches, $customers, $vehicles): void
    {
        OrderOfService::factory(1)
            ->cancelled()
            ->sequence(fn () => [
                'branch_id' => $branches->random()->id,
                'customer_id' => $customers->random()->id,
                'vehicle_id' => $vehicles->random()->id,
            ])
            ->create();
    }
}
