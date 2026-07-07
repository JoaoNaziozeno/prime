<?php

use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Customer Model', function () {

    test('create customer with factory', function () {
        $customer = Customer::factory()->create();

        expect($customer)->toBeInstanceOf(Customer::class);
        expect($customer->name)->not->toBeEmpty();
        expect($customer->status)->toBe(Customer::STATUS_ACTIVE);
    });

    test('customer can be active, inactive or suspended', function () {
        $active = Customer::factory()->active()->create();
        $inactive = Customer::factory()->inactive()->create();
        $suspended = Customer::factory()->suspended()->create();

        expect($active->status)->toBe(Customer::STATUS_ACTIVE);
        expect($inactive->status)->toBe(Customer::STATUS_INACTIVE);
        expect($suspended->status)->toBe(Customer::STATUS_SUSPENDED);
    });

    test('customer can be individual or company', function () {
        $individual = Customer::factory()->individual()->create();
        $company = Customer::factory()->company()->create();

        expect($individual->type)->toBe(Customer::TYPE_INDIVIDUAL);
        expect($company->type)->toBe(Customer::TYPE_COMPANY);
    });

    test('customer email is lowercased', function () {
        $customer = Customer::factory()->create(['email' => 'JOHN@EXAMPLE.COM']);

        expect($customer->email)->toBe('john@example.com');
    });

    test('customer cpf_cnpj is cleaned', function () {
        $customer = Customer::factory()->create(['cpf_cnpj' => '123.456.789-00']);

        expect($customer->cpf_cnpj)->toBe('12345678900');
    });

    test('customer scopes filter correctly', function () {
        Customer::factory(5)->active()->create();
        Customer::factory(3)->inactive()->create();
        Customer::factory(2)->suspended()->create();

        expect(Customer::active()->count())->toBe(5);
        expect(Customer::inactive()->count())->toBe(3);
        expect(Customer::suspended()->count())->toBe(2);
    });

    test('customer can activate', function () {
        $customer = Customer::factory()->inactive()->create();

        $customer->activate();

        expect($customer->status)->toBe(Customer::STATUS_ACTIVE);
        expect($customer->activated_at)->not->toBeNull();
    });

    test('customer can suspend', function () {
        $customer = Customer::factory()->active()->create();

        $customer->suspend();

        expect($customer->status)->toBe(Customer::STATUS_SUSPENDED);
        expect($customer->suspended_at)->not->toBeNull();
    });

    test('customer metadata can be stored', function () {
        $customer = Customer::factory()->create();

        $customer->setMetadataValue('custom_field', 'custom_value');

        expect($customer->getMetadataValue('custom_field'))->toBe('custom_value');
    });

});

describe('Branch Model', function () {

    test('create branch with factory', function () {
        $branch = Branch::factory()->create();

        expect($branch)->toBeInstanceOf(Branch::class);
        expect($branch->name)->not->toBeEmpty();
        expect($branch->code)->not->toBeEmpty();
    });

    test('branch can be active or inactive', function () {
        $active = Branch::factory()->active()->create();
        $inactive = Branch::factory()->inactive()->create();

        expect($active->status)->toBe(Branch::STATUS_ACTIVE);
        expect($inactive->status)->toBe(Branch::STATUS_INACTIVE);
    });

    test('branch scopes filter correctly', function () {
        Branch::factory(3)->active()->create();
        Branch::factory(2)->inactive()->create();

        expect(Branch::active()->count())->toBe(3);
        expect(Branch::inactive()->count())->toBe(2);
    });

    test('branch has many vehicles', function () {
        $branch = Branch::factory()->create();
        Vehicle::factory(3)->forBranch($branch)->create();

        expect($branch->vehicles()->count())->toBe(3);
    });

    test('branch can activate', function () {
        $branch = Branch::factory()->inactive()->create();

        $branch->activate();

        expect($branch->status)->toBe(Branch::STATUS_ACTIVE);
    });

});

describe('Driver Model', function () {

    test('create driver with factory', function () {
        $driver = Driver::factory()->create();

        expect($driver)->toBeInstanceOf(Driver::class);
        expect($driver->name)->not->toBeEmpty();
        expect($driver->cpf)->not->toBeEmpty();
        expect($driver->cnh)->not->toBeEmpty();
    });

    test('driver can be active, inactive or suspended', function () {
        $active = Driver::factory()->active()->create();
        $inactive = Driver::factory()->inactive()->create();
        $suspended = Driver::factory()->suspended()->create();

        expect($active->status)->toBe(Driver::STATUS_ACTIVE);
        expect($inactive->status)->toBe(Driver::STATUS_INACTIVE);
        expect($suspended->status)->toBe(Driver::STATUS_SUSPENDED);
    });

    test('driver email is lowercased', function () {
        $driver = Driver::factory()->create(['email' => 'JOHN@EXAMPLE.COM']);

        expect($driver->email)->toBe('john@example.com');
    });

    test('driver cpf is cleaned', function () {
        $driver = Driver::factory()->create(['cpf' => '123.456.789-00']);

        expect($driver->cpf)->toBe('12345678900');
    });

    test('driver scopes filter correctly', function () {
        Driver::factory(3)->active()->create();
        Driver::factory(2)->inactive()->create();
        Driver::factory(1)->suspended()->create();

        expect(Driver::active()->count())->toBe(3);
        expect(Driver::inactive()->count())->toBe(2);
        expect(Driver::suspended()->count())->toBe(1);
    });

    test('driver with expired cnh detected', function () {
        $driver = Driver::factory()->withExpiredCnh()->create();

        expect($driver->isCnhExpired())->toBeTrue();
        expect($driver->is_cnh_expired)->toBeTrue();
    });

    test('driver with expiring cnh detected', function () {
        $driver = Driver::factory()->withExpiringCnh()->create();

        expect($driver->isCnhExpiring())->toBeTrue();
        expect($driver->is_cnh_expiring)->toBeTrue();
    });

    test('driver can operate if active and CNH valid', function () {
        $driverActive = Driver::factory()->active()->create();
        $driverSuspended = Driver::factory()->suspended()->create();
        $driverExpired = Driver::factory()->active()->withExpiredCnh()->create();

        expect($driverActive->canOperate())->toBeTrue();
        expect($driverSuspended->canOperate())->toBeFalse();
        expect($driverExpired->canOperate())->toBeFalse();
    });

    test('driver days until cnh expiration calculated', function () {
        $driver = Driver::factory()->create(['cnh_expiration' => now()->addDays(10)]);

        $days = $driver->getDaysUntilCnhExpiration();

        expect($days)->toBeLessThanOrEqual(10);
        expect($days)->toBeGreaterThanOrEqual(9);
    });

});

describe('Vehicle Model', function () {

    test('create vehicle with factory', function () {
        $vehicle = Vehicle::factory()->create();

        expect($vehicle)->toBeInstanceOf(Vehicle::class);
        expect($vehicle->plate)->not->toBeEmpty();
        expect($vehicle->status)->toBe(Vehicle::STATUS_ACTIVE);
    });

    test('vehicle can be active, inactive or maintenance', function () {
        $active = Vehicle::factory()->active()->create();
        $inactive = Vehicle::factory()->inactive()->create();
        $maintenance = Vehicle::factory()->maintenance()->create();

        expect($active->status)->toBe(Vehicle::STATUS_ACTIVE);
        expect($inactive->status)->toBe(Vehicle::STATUS_INACTIVE);
        expect($maintenance->status)->toBe(Vehicle::STATUS_MAINTENANCE);
    });

    test('vehicle plate is uppercased', function () {
        $vehicle = Vehicle::factory()->create(['plate' => 'abc-1234']);

        expect($vehicle->plate)->toBe('ABC-1234');
    });

    test('vehicle scopes filter correctly', function () {
        Vehicle::factory(4)->active()->create();
        Vehicle::factory(2)->inactive()->create();
        Vehicle::factory(1)->maintenance()->create();

        expect(Vehicle::active()->count())->toBe(4);
        expect(Vehicle::inactive()->count())->toBe(2);
        expect(Vehicle::maintenance()->count())->toBe(1);
    });

    test('vehicle can be truck, van or car', function () {
        $truck = Vehicle::factory()->truck()->create();
        $van = Vehicle::factory()->van()->create();
        $car = Vehicle::factory()->car()->create();

        expect($truck->type)->toBe(Vehicle::TYPE_TRUCK);
        expect($van->type)->toBe(Vehicle::TYPE_VAN);
        expect($car->type)->toBe(Vehicle::TYPE_CAR);
    });

    test('vehicle with expired license detected', function () {
        $vehicle = Vehicle::factory()->withExpiredLicense()->create();

        expect($vehicle->isLicenseExpired())->toBeTrue();
        expect($vehicle->is_license_expired)->toBeTrue();
    });

    test('vehicle with expiring license detected', function () {
        $vehicle = Vehicle::factory()->withExpiringLicense()->create();

        expect($vehicle->isLicenseExpiring())->toBeTrue();
        expect($vehicle->is_license_expiring)->toBeTrue();
    });

    test('vehicle can operate if active and license valid', function () {
        $vehicleActive = Vehicle::factory()->active()->create();
        $vehicleInactive = Vehicle::factory()->inactive()->create();
        $vehicleExpired = Vehicle::factory()->active()->withExpiredLicense()->create();

        expect($vehicleActive->canOperate())->toBeTrue();
        expect($vehicleInactive->canOperate())->toBeFalse();
        expect($vehicleExpired->canOperate())->toBeFalse();
    });

    test('vehicle can be sent to maintenance', function () {
        $vehicle = Vehicle::factory()->active()->create();

        $vehicle->sendToMaintenance();

        expect($vehicle->status)->toBe(Vehicle::STATUS_MAINTENANCE);
    });

    test('vehicle belongs to branch', function () {
        $branch = Branch::factory()->create();
        $vehicle = Vehicle::factory()->forBranch($branch)->create();

        expect($vehicle->branch_id)->toBe($branch->id);
        expect($vehicle->branch->id)->toBe($branch->id);
    });

});

describe('Relationships', function () {

    test('branch has many vehicles', function () {
        $branch = Branch::factory()->create();
        $vehicles = Vehicle::factory(3)->forBranch($branch)->create();

        expect($branch->vehicles()->count())->toBe(3);
        expect($branch->getActiveVehiclesCount())->toBe(3);
    });

    test('vehicle belongs to branch', function () {
        $branch = Branch::factory()->create();
        $vehicle = Vehicle::factory()->forBranch($branch)->create();

        expect($vehicle->branch->id)->toBe($branch->id);
    });

});
