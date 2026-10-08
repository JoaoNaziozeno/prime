<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Role;
use Illuminate\Support\Facades\DB;

describe('User Management CRUD Feature Tests', function () {

    beforeEach(function () {
        $this->admin = User::factory()->superAdmin()->create();
        $this->regularUser = User::factory()->create(['role' => 'user']);
        
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'user-crud-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        // Link admin and user to the company
        $this->company->users()->attach($this->admin->id, ['role' => 'admin']);
        $this->company->users()->attach($this->regularUser->id, ['role' => 'user']);

        // Create a role in tenant DB
        $this->role = Role::create(['name' => 'Mecânico', 'description' => 'Mecânico da Oficina']);
    });

    test('admin can list users associated with the company', function () {
        $url = "http://user-crud-tenant.prime-erp.local/api/users";

        $response = $this->actingAs($this->admin)->getJson($url);

        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => $this->admin->email]);
        $response->assertJsonFragment(['email' => $this->regularUser->email]);
    });

    test('regular user cannot list company users', function () {
        $url = "http://user-crud-tenant.prime-erp.local/api/users";

        $response = $this->actingAs($this->regularUser)->getJson($url);

        $response->assertStatus(403);
    });

    test('admin can create and link a new user', function () {
        $url = "http://user-crud-tenant.prime-erp.local/api/users";

        $response = $this->actingAs($this->admin)->postJson($url, [
            'name' => 'Novo Mecânico',
            'email' => 'mecanico@prime-erp.com',
            'password' => 'password123',
            'role' => 'user',
            'roles' => ['Mecânico'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['email' => 'mecanico@prime-erp.com']);

        // Check central database
        $newUser = User::where('email', 'mecanico@prime-erp.com')->first();
        expect($newUser)->not->toBeNull();

        // Check pivot table link
        expect($this->company->users()->where('users.id', $newUser->id)->exists())->toBeTrue();

        // Check tenant roles assigned
        $hasRole = DB::connection('tenant')
            ->table('tenant_user_roles')
            ->where('user_id', $newUser->id)
            ->where('role_id', $this->role->id)
            ->exists();
        expect($hasRole)->toBeTrue();
    });

    test('admin can update user roles and status', function () {
        $newUser = User::factory()->create(['role' => 'user']);
        $this->company->users()->attach($newUser->id, ['role' => 'user']);

        $url = "http://user-crud-tenant.prime-erp.local/api/users/{$newUser->id}";

        $response = $this->actingAs($this->admin)->putJson($url, [
            'name' => 'Mecânico Atualizado',
            'email' => $newUser->email,
            'role' => 'admin',
            'is_active' => false,
            'roles' => ['Mecânico'],
        ]);

        $response->assertStatus(200);
        $newUser->refresh();
        expect($newUser->name)->toBe('Mecânico Atualizado');
        expect($newUser->role)->toBe('admin');
        expect($newUser->is_active)->toBeFalse();
    });

    test('admin can disassociate a user from the company', function () {
        $newUser = User::factory()->create(['role' => 'user']);
        $this->company->users()->attach($newUser->id, ['role' => 'user']);

        $url = "http://user-crud-tenant.prime-erp.local/api/users/{$newUser->id}";

        $response = $this->actingAs($this->admin)->deleteJson($url);

        $response->assertStatus(200);

        // Check pivot table link is gone
        expect($this->company->users()->where('users.id', $newUser->id)->exists())->toBeFalse();
    });

});
