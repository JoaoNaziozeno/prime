<?php

use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Customer;
use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Services\Master\TotpService;
use Illuminate\Support\Facades\DB;

describe('Two-Factor Authentication (2FA) Master Flow', function () {

    beforeEach(function () {
        $this->user = User::factory()->create(['email' => '2fa-test@prime-erp.com']);
        $this->totpService = app(TotpService::class);

        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'rbac-tenant-a',
        ]);
        tenancy()->initialize($this->tenant);
    });

    test('can generate 2FA secret and recovery codes via API', function () {
        $url = "http://rbac-tenant-a.prime-erp.local/api/user/two-factor-authentication";

        $response = $this->actingAs($this->user)->postJson($url);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'secret',
            'qr_code_url',
            'recovery_codes',
        ]);

        $this->user->refresh();
        expect($this->user->two_factor_secret)->not->toBeNull();
        expect($this->user->two_factor_confirmed_at)->toBeNull();
    });

    test('can confirm 2FA with valid TOTP code', function () {
        $secret = $this->totpService->generateSecret();
        $this->user->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ]);

        // Generate current valid code slice
        $currentTimeSlice = floor(time() / 30);
        $method = new ReflectionMethod($this->totpService, 'calculateCode');
        $method->setAccessible(true);
        $validCode = $method->invoke($this->totpService, $secret, $currentTimeSlice);

        $url = "http://rbac-tenant-a.prime-erp.local/api/user/confirmed-two-factor-authentication";

        $response = $this->actingAs($this->user)->postJson($url, [
            'code' => $validCode,
        ]);

        $response->assertStatus(200);
        expect($this->user->fresh()->two_factor_confirmed_at)->not->toBeNull();
    });

    test('rejects confirmation with invalid TOTP code', function () {
        $secret = $this->totpService->generateSecret();
        $this->user->update([
            'two_factor_secret' => $secret,
        ]);

        $url = "http://rbac-tenant-a.prime-erp.local/api/user/confirmed-two-factor-authentication";

        $response = $this->actingAs($this->user)->postJson($url, [
            'code' => '999999', // invalid
        ]);

        $response->assertStatus(422);
        expect($this->user->fresh()->two_factor_confirmed_at)->toBeNull();
    });

    test('can disable 2FA via API', function () {
        $this->user->update([
            'two_factor_secret' => 'SECRET123',
            'two_factor_confirmed_at' => now(),
        ]);

        $url = "http://rbac-tenant-a.prime-erp.local/api/user/two-factor-authentication";

        $response = $this->actingAs($this->user)->deleteJson($url);

        $response->assertStatus(200);
        $this->user->refresh();
        expect($this->user->two_factor_secret)->toBeNull();
        expect($this->user->two_factor_confirmed_at)->toBeNull();
    });

});

describe('RBAC (Roles and Permissions) Tenant Flow', function () {

    beforeEach(function () {
        $this->admin = User::factory()->superAdmin()->create();
        $this->regularUser = User::factory()->create(['role' => 'user']);
        
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'rbac-tenant-b',
        ]);
        tenancy()->initialize($this->tenant);

        // Pre-create some permissions in Tenant DB
        $this->p1 = Permission::create(['name' => 'view_dashboard', 'description' => 'Ver painel']);
        $this->p2 = Permission::create(['name' => 'edit_orders', 'description' => 'Editar OS']);
    });

    test('super_admin and admin bypass hasTenantPermission checks', function () {
        expect($this->admin->hasTenantPermission('view_dashboard'))->toBeTrue();
        expect($this->admin->hasTenantPermission('non_existent_permission'))->toBeTrue();
    });

    test('regular user does not have permission by default', function () {
        expect($this->regularUser->hasTenantPermission('view_dashboard'))->toBeFalse();
    });

    test('can create role with permissions via API', function () {
        $url = "http://rbac-tenant-b.prime-erp.local/api/rbac/roles";

        $response = $this->actingAs($this->admin)->postJson($url, [
            'name' => 'Mecânico Senior',
            'description' => 'Mecânico com permissões de edição',
            'permissions' => ['edit_orders'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'Mecânico Senior']);

        $role = Role::where('name', 'Mecânico Senior')->first();
        expect($role->permissions->pluck('name')->toArray())->toContain('edit_orders');
    });

    test('can assign and revoke roles to users', function () {
        $role = Role::create(['name' => 'Mecânico']);
        $role->permissions()->attach($this->p1->id);

        $urlAssign = "http://rbac-tenant-b.prime-erp.local/api/rbac/assign";
        $urlRevoke = "http://rbac-tenant-b.prime-erp.local/api/rbac/revoke";

        // Assign role
        $response = $this->actingAs($this->admin)->postJson($urlAssign, [
            'user_id' => $this->regularUser->id,
            'role_name' => 'Mecânico',
        ]);
        $response->assertStatus(200);

        expect($this->regularUser->hasTenantPermission('view_dashboard'))->toBeTrue();
        expect($this->regularUser->hasTenantPermission('edit_orders'))->toBeFalse();

        // Revoke role
        $responseRevoke = $this->actingAs($this->admin)->postJson($urlRevoke, [
            'user_id' => $this->regularUser->id,
            'role_name' => 'Mecânico',
        ]);
        $responseRevoke->assertStatus(200);

        expect($this->regularUser->hasTenantPermission('view_dashboard'))->toBeFalse();
    });

});

describe('Activity Logging (Audit Logs) Tenant Flow', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'rbac-tenant-c',
        ]);
        tenancy()->initialize($this->tenant);
    });

    test('automatically logs create, update and delete events on Customer', function () {
        // Log in user to populate user_id in audit log
        $this->actingAs($this->user);

        // 1. Create
        $customer = Customer::create([
            'name' => 'Joao Naziozeno',
            'email' => 'joao@naziozeno.com',
            'type' => 'individual',
            'cpf_cnpj' => '12345678901',
        ]);

        expect(AuditLog::count())->toBe(1);
        $logCreate = AuditLog::first();
        expect($logCreate->action)->toBe('create');
        expect($logCreate->auditable_type)->toBe(Customer::class);
        expect($logCreate->new_values['name'])->toBe('Joao Naziozeno');
        expect($logCreate->user_id)->toBe($this->user->id);

        // 2. Update
        $customer->update(['name' => 'Joao Naziozeno Editado']);
        expect(AuditLog::count())->toBe(2);
        $logUpdate = AuditLog::orderBy('id', 'desc')->first();
        expect($logUpdate->action)->toBe('update');
        expect($logUpdate->old_values['name'])->toBe('Joao Naziozeno');
        expect($logUpdate->new_values['name'])->toBe('Joao Naziozeno Editado');

        // 3. Delete
        $customer->delete();
        expect(AuditLog::count())->toBe(3);
        $logDelete = AuditLog::orderBy('id', 'desc')->first();
        expect($logDelete->action)->toBe('delete');
        expect($logDelete->old_values['name'])->toBe('Joao Naziozeno Editado');
    });

    test('can retrieve audit logs via API', function () {
        $this->actingAs($this->user);
        Customer::create([
            'name' => 'Audit Test Customer',
            'email' => 'audit@test.com',
            'type' => 'company',
            'cpf_cnpj' => '12345678000100',
        ]);

        $url = "http://rbac-tenant-c.prime-erp.local/api/audit-logs";

        $response = $this->actingAs($this->user)->getJson($url);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'action' => 'create',
            'auditable_type' => Customer::class,
        ]);
    });

});
