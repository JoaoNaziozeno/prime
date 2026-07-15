<?php

use App\Models\Tenant\WarehouseLocation;
use App\Models\Tenant\Product;
use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('WarehouseLocation Model', function () {

    test('can create warehouse location with factory', function () {
        $location = WarehouseLocation::factory()->create();

        expect($location)->toBeInstanceOf(WarehouseLocation::class);
        expect($location->name)->not->toBeEmpty();
        expect($location->code)->not->toBeEmpty();
    });

    test('location code is unique', function () {
        $location1 = WarehouseLocation::factory()->create(['code' => 'LOC-A1']);
        
        expect(function () {
            WarehouseLocation::factory()->create(['code' => 'LOC-A1']);
        })->toThrow(\Illuminate\Database\QueryException::class);
    });

    test('can list products in location', function () {
        $location = WarehouseLocation::factory()->create();
        Product::factory(3)->create(['warehouse_location_id' => $location->id]);

        expect($location->products)->toHaveCount(3);
        expect($location->products->first())->toBeInstanceOf(Product::class);
    });
});

describe('WarehouseLocation Controller API', function () {

    test('can list warehouse locations through API', function () {
        $user = User::factory()->superAdmin()->create();
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->active()->create([
            'company_id' => $company->id,
            'slug' => 'test-location-tenant',
        ]);

        // Ativa tenancy para criar dados no banco correto
        tenancy()->initialize($tenant);
        WarehouseLocation::factory(3)->create();

        // Envia requisição simulando host do tenant
        $response = $this->actingAs($user)
            ->getJson('http://test-location-tenant.prime-erp.local/api/warehouse-locations');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    });

    test('can create warehouse location through API', function () {
        $user = User::factory()->superAdmin()->create();
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->active()->create([
            'company_id' => $company->id,
            'slug' => 'test-location-create',
        ]);

        $response = $this->actingAs($user)
            ->postJson('http://test-location-create.prime-erp.local/api/warehouse-locations', [
                'name' => 'Corredor B - Prateleira 2',
                'code' => 'LOC-B2',
                'description' => 'Armazenamento de filtros',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'name' => 'Corredor B - Prateleira 2',
            'code' => 'LOC-B2',
        ]);

        // Verifica persistência
        tenancy()->initialize($tenant);
        $location = WarehouseLocation::where('code', 'LOC-B2')->first();
        expect($location)->not->toBeNull();
        expect($location->description)->toBe('Armazenamento de filtros');
    });

    test('can delete empty warehouse location', function () {
        $user = User::factory()->superAdmin()->create();
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->active()->create([
            'company_id' => $company->id,
            'slug' => 'test-location-delete',
        ]);

        tenancy()->initialize($tenant);
        $location = WarehouseLocation::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("http://test-location-delete.prime-erp.local/api/warehouse-locations/{$location->id}");

        $response->assertStatus(204);
        
        expect(WarehouseLocation::find($location->id))->toBeNull();
    });

    test('cannot delete warehouse location with products', function () {
        $user = User::factory()->superAdmin()->create();
        $company = Company::factory()->create();
        $tenant = Tenant::factory()->active()->create([
            'company_id' => $company->id,
            'slug' => 'test-location-delete-fail',
        ]);

        tenancy()->initialize($tenant);
        $location = WarehouseLocation::factory()->create();
        Product::factory()->create(['warehouse_location_id' => $location->id]);

        $response = $this->actingAs($user)
            ->deleteJson("http://test-location-delete-fail.prime-erp.local/api/warehouse-locations/{$location->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => 'Não é possível excluir uma localização contendo produtos associados.'
        ]);

        expect(WarehouseLocation::find($location->id))->not->toBeNull();
    });
});
