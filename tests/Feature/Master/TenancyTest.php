<?php

use App\Models\Master\Company;
use App\Models\Master\Tenant;
use App\Models\Master\User;
use Illuminate\Foundation\Testing\RefreshDatabase;



describe('Tenant Resolution', function () {

    test('tenant can be resolved by slug', function () {
        $user = User::factory()->superAdmin()->create();
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->active()->create([
            'company_id' => $company->id,
            'slug' => 'test-company',
        ]);

        // Simulate initializing tenancy
        tenancy()->initialize($tenant);

        expect(tenancy()->tenant->id)->toBe($tenant->id);
        expect(tenancy()->tenant->slug)->toBe('test-company');
    });

    test('inactive tenant cannot be accessed', function () {
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->create([
            'company_id' => $company->id,
            'slug' => 'inactive-tenant',
            'status' => 'paused',
        ]);

        // Should fail to resolve inactive tenant
        $foundTenant = Tenant::where('slug', 'inactive-tenant')->active()->first();
        expect($foundTenant)->toBeNull();
    });

    test('tenant has correct database configuration', function () {
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->active()->create([
            'company_id' => $company->id,
            'slug' => 'test-db-config',
            'db_host' => 'localhost',
            'db_port' => 3306,
            'db_driver' => 'mysql',
        ]);

        $config = $tenant->getConnectionConfig();

        expect($config['host'])->toBe('localhost');
        expect($config['port'])->toBe(3306);
        expect($config['driver'])->toBe('mysql');
        expect($config['database'])->toContain('tenant');
    });

    test('tenant slug is auto-generated without collision', function () {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $tenant1 = Tenant::factory()->create([
            'company_id' => $company1->id,
            'slug' => null, // Will be auto-generated
        ]);

        $tenant2 = Tenant::factory()->create([
            'company_id' => $company2->id,
            'slug' => null, // Will be auto-generated
        ]);

        expect($tenant1->slug)->not->toBeNull();
        expect($tenant2->slug)->not->toBeNull();
        expect($tenant1->slug)->not->toBe($tenant2->slug);
    });

});

describe('Multi-Tenant Isolation', function () {

    test('company can have one active tenant', function () {
        $company = Company::factory()->create();
        $tenant1 = Tenant::factory()->active()->create(['company_id' => $company->id]);
        $tenant2 = Tenant::factory()->create([
            'company_id' => $company->id,
            'status' => 'setup',
        ]);

        $activeTenant = $company->activeTenant;

        expect($activeTenant->id)->toBe($tenant1->id);
    });

    test('tenant relationships are maintained', function () {
        $user = User::factory()->superAdmin()->create();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $tenant = Tenant::factory()->active()->create(['company_id' => $company->id]);

        expect($tenant->company()->first()->id)->toBe($company->id);
        expect($company->tenants()->first()->id)->toBe($tenant->id);
    });

    test('tenant can be paused', function () {
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->active()->create(['company_id' => $company->id]);

        $tenant->pause();

        expect($tenant->status)->toBe('paused');
        expect($tenant->refresh()->status)->toBe('paused');
    });

    test('tenant can be marked as deleted', function () {
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->active()->create(['company_id' => $company->id]);

        $tenant->markAsDeleted();

        expect($tenant->status)->toBe('deleted');
    });

});

describe('Tenant Settings', function () {

    test('tenant can store and retrieve settings', function () {
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->create(['company_id' => $company->id]);

        $tenant->setSetting('language', 'pt_BR');
        $tenant->setSetting('timezone', 'America/Sao_Paulo');

        expect($tenant->getSetting('language'))->toBe('pt_BR');
        expect($tenant->getSetting('timezone'))->toBe('America/Sao_Paulo');
    });

    test('tenant settings persist after refresh', function () {
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->create(['company_id' => $company->id]);

        $tenant->setSetting('currency', 'BRL');
        $tenant->save();

        $refreshed = Tenant::find($tenant->id);
        expect($refreshed->getSetting('currency'))->toBe('BRL');
    });

});

describe('Tenant Database', function () {

    test('tenant database name is correctly formed', function () {
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->create([
            'company_id' => $company->id,
            'database_name' => 'tenant_empresa_demo',
        ]);

        expect($tenant->database_name)->toBe('tenant_empresa_demo');
    });

    test('tenant connection string can be built', function () {
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->create([
            'company_id' => $company->id,
            'db_driver' => 'mysql',
            'db_host' => 'localhost',
            'db_port' => 3306,
            'db_username' => 'root',
            'db_password' => 'secret',
            'database_name' => 'test_db',
        ]);

        $connectionString = $tenant->getConnectionString();

        expect($connectionString)->toContain('mysql://');
        expect($connectionString)->toContain('root');
        expect($connectionString)->toContain('test_db');
    });

});

describe('Tenant Audit', function () {

    test('tenant creation is audited', function () {
        $user = User::factory()->superAdmin()->create();
        $company = Company::factory()->create();

        $tenant = Tenant::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);

        expect($tenant->created_by)->toBe($user->id);
    });

    test('tenant updates are tracked', function () {
        $user = User::factory()->superAdmin()->create();
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);

        $tenant->updated_by = $user->id;
        $tenant->save();

        expect($tenant->refresh()->updated_by)->toBe($user->id);
    });

});
