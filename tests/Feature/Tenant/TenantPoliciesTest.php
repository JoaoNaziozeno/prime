<?php

use App\Models\Master\User;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;



describe('Customer Policy', function () {

    test('any user can view customers', function () {
        $user = User::factory()->user()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($user)
            ->assertTrue($user->can('view', $customer));
    });

    test('user can create customer', function () {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->assertTrue($user->can('create', Customer::class));
    });

    test('only admin can delete customer', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->user()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($admin)
            ->assertTrue($admin->can('delete', $customer));

        $this->actingAs($user)
            ->assertFalse($user->can('delete', $customer));
    });

});

describe('Branch Policy', function () {

    test('any user can view branches', function () {
        $user = User::factory()->user()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($user)
            ->assertTrue($user->can('view', $branch));
    });

    test('only admin can create branch', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($admin)
            ->assertTrue($admin->can('create', Branch::class));

        $this->actingAs($user)
            ->assertFalse($user->can('create', Branch::class));
    });

    test('cannot delete branch with active vehicles', function () {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();
        Vehicle::factory()->active()->forBranch($branch)->create();

        $this->actingAs($admin)
            ->assertFalse($admin->can('delete', $branch));
    });

    test('can delete empty branch', function () {
        $admin = User::factory()->admin()->create();
        $branch = Branch::factory()->create();

        $this->actingAs($admin)
            ->assertTrue($admin->can('delete', $branch));
    });

});

describe('Driver Policy', function () {

    test('any user can view drivers', function () {
        $user = User::factory()->user()->create();
        $driver = Driver::factory()->create();

        $this->actingAs($user)
            ->assertTrue($user->can('view', $driver));
    });

    test('only admin can create driver', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($admin)
            ->assertTrue($admin->can('create', Driver::class));

        $this->actingAs($user)
            ->assertFalse($user->can('create', Driver::class));
    });

    test('only admin can suspend driver', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->user()->create();
        $driver = Driver::factory()->create();

        $this->actingAs($admin)
            ->assertTrue($admin->can('suspend', $driver));

        $this->actingAs($user)
            ->assertFalse($user->can('suspend', $driver));
    });

});

describe('Vehicle Policy', function () {

    test('any user can view vehicles', function () {
        $user = User::factory()->user()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($user)
            ->assertTrue($user->can('view', $vehicle));
    });

    test('only admin can create vehicle', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($admin)
            ->assertTrue($admin->can('create', Vehicle::class));

        $this->actingAs($user)
            ->assertFalse($user->can('create', Vehicle::class));
    });

    test('only admin can send to maintenance', function () {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->user()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($admin)
            ->assertTrue($admin->can('maintenance', $vehicle));

        $this->actingAs($user)
            ->assertFalse($user->can('maintenance', $vehicle));
    });

});
