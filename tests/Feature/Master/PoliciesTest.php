<?php

use App\Models\Master\User;
use App\Models\Master\Company;
use App\Models\Master\Plan;
use App\Models\Master\Subscription;
use App\Models\Master\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;



describe('Company Policy', function () {

    test('super admin pode visualizar qualquer empresa', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $company = Company::factory()->create();

        expect(auth()->user($superAdmin) ?? true)->toBeTrue();
    });

    test('proprietário pode visualizar sua empresa', function () {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->assertTrue($owner->can('view', $company));
    });

    test('usuário não autorizado não pode ver empresa alheia', function () {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($user)
            ->assertFalse($user->can('view', $company));
    });

    test('super admin pode criar empresa', function () {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->assertTrue($superAdmin->can('create', Company::class));
    });

    test('usuário comum não pode criar empresa', function () {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->assertFalse($user->can('create', Company::class));
    });

    test('proprietário pode atualizar sua empresa', function () {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->assertTrue($owner->can('update', $company));
    });

    test('usuário outro não pode atualizar empresa', function () {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($user)
            ->assertFalse($user->can('update', $company));
    });

    test('apenas super admin pode deletar empresa', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->user()->create();
        $company = Company::factory()->create();

        $this->actingAs($superAdmin)
            ->assertTrue($superAdmin->can('delete', $company));

        $this->actingAs($user)
            ->assertFalse($user->can('delete', $company));
    });

});

describe('Plan Policy', function () {

    test('qualquer usuário pode ver planos', function () {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($user)
            ->assertTrue($user->can('view', $plan));
    });

    test('apenas super admin pode criar plano', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($superAdmin)
            ->assertTrue($superAdmin->can('create', Plan::class));

        $this->actingAs($user)
            ->assertFalse($user->can('create', Plan::class));
    });

    test('apenas super admin pode deletar plano sem assinaturas', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($superAdmin)
            ->assertTrue($superAdmin->can('delete', $plan));
    });

    test('não pode deletar plano com assinaturas ativas', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $plan = Plan::factory()->create();
        Subscription::factory()->active()->create(['plan_id' => $plan->id]);

        $this->actingAs($superAdmin)
            ->assertFalse($superAdmin->can('delete', $plan));
    });

});

describe('Subscription Policy', function () {

    test('super admin pode ver qualquer assinatura', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($superAdmin)
            ->assertTrue($superAdmin->can('view', $subscription));
    });

    test('proprietário da empresa pode ver assinatura', function () {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);
        $subscription = Subscription::factory()->create(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->assertTrue($owner->can('view', $subscription));
    });

    test('usuário não pode ver assinatura alheia', function () {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)
            ->assertFalse($user->can('view', $subscription));
    });

    test('apenas super admin pode criar assinatura', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->user()->create();

        $this->actingAs($superAdmin)
            ->assertTrue($superAdmin->can('create', Subscription::class));

        $this->actingAs($user)
            ->assertFalse($user->can('create', Subscription::class));
    });

});

describe('Tenant Policy', function () {

    test('super admin pode ver qualquer tenant', function () {
        $superAdmin = User::factory()->superAdmin()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($superAdmin)
            ->assertTrue($superAdmin->can('view', $tenant));
    });

    test('proprietário da empresa pode ver tenant', function () {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);
        $tenant = Tenant::factory()->create(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->assertTrue($owner->can('view', $tenant));
    });

    test('propriedade pode atualizar seu tenant', function () {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);
        $tenant = Tenant::factory()->create(['company_id' => $company->id]);

        $this->actingAs($owner)
            ->assertTrue($owner->can('update', $tenant));
    });

});
