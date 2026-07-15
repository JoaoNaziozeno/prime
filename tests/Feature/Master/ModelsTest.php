<?php

use App\Models\Master\User;
use App\Models\Master\Company;
use App\Models\Master\Plan;
use Database\Factories\Master\UserFactory;
use Database\Factories\Master\CompanyFactory;
use Database\Factories\Master\PlanFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;



describe('User Model', function () {

    test('pode criar usuário', function () {
        $user = User::factory()->create();

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBeGreaterThan(0)
            ->and($user->email)->toContain('@');
    });

    test('pode criar super admin', function () {
        $user = User::factory()->superAdmin()->create();

        expect($user->role)->toBe('super_admin')
            ->and($user->isSuperAdmin())->toBeTrue();
    });

    test('pode criar admin', function () {
        $user = User::factory()->admin()->create();

        expect($user->role)->toBe('admin')
            ->and($user->isAdmin())->toBeTrue();
    });

    test('pode criar usuário inativo', function () {
        $user = User::factory()->inactive()->create();

        expect($user->is_active)->toBeFalse();
    });

    test('email é sempre minúsculo', function () {
        $user = User::factory()->create([
            'email' => 'TEST@EMAIL.COM',
        ]);

        expect($user->email)->toBe('test@email.com');
    });

    test('pode registrar login', function () {
        $user = User::factory()->create();
        $user->recordLogin('192.168.1.1');

        expect($user->last_login_at)->not->toBeNull()
            ->and($user->last_login_ip)->toBe('192.168.1.1');
    });

    test('scope active funciona', function () {
        User::factory()->inactive()->create();
        $active = User::factory()->count(3)->create(['is_active' => true]);

        $result = User::active()->get();

        expect($result)->toHaveCount(3);
    });

    test('scope superAdmin funciona', function () {
        User::factory()->admin()->create();
        $super = User::factory()->superAdmin()->create();

        $result = User::superAdmin()->get();

        expect($result)->toHaveCount(1)
            ->and($result->first()->id)->toBe($super->id);
    });

});

describe('Company Model', function () {

    test('pode criar empresa', function () {
        $company = Company::factory()->create();

        expect($company)->toBeInstanceOf(Company::class)
            ->and($company->status)->toBe('active');
    });

    test('empresa possui proprietário', function () {
        $company = Company::factory()->create();

        expect($company->owner)->toBeInstanceOf(User::class);
    });

    test('empresa pode estar suspensa', function () {
        $company = Company::factory()->suspended()->create();

        expect($company->status)->toBe('suspended')
            ->and($company->isSuspended())->toBeTrue();
    });

    test('pode ativar empresa suspensa', function () {
        $company = Company::factory()->suspended()->create();
        $company->activate();

        expect($company->isActive())->toBeTrue();
    });

    test('pode suspender empresa ativa', function () {
        $company = Company::factory()->active()->create();
        $company->suspend();

        expect($company->isSuspended())->toBeTrue();
    });

    test('CNPJ sem formatação', function () {
        $company = Company::factory()->create([
            'cnpj' => '11.222.333/0001-81',
        ]);

        expect($company->cnpj)->toBe('11222333000181');
    });

    test('scope active funciona', function () {
        Company::factory()->suspended()->create();
        Company::factory()->count(2)->active()->create();

        $result = Company::active()->get();

        expect($result)->toHaveCount(2);
    });

});

describe('Plan Model', function () {

    test('pode criar plano', function () {
        $plan = Plan::factory()->create();

        expect($plan)->toBeInstanceOf(Plan::class);
    });

    test('pode criar plano básico', function () {
        $plan = Plan::factory()->basic()->create();

        expect($plan->name)->toBe('Plano Básico')
            ->and($plan->price)->toBe(99.00)
            ->and($plan->max_users)->toBe(5);
    });

    test('pode criar plano profissional', function () {
        $plan = Plan::factory()->professional()->create();

        expect($plan->name)->toBe('Plano Profissional')
            ->and($plan->price)->toBe(299.00)
            ->and($plan->has_api_access)->toBeTrue();
    });

    test('pode criar plano empresarial', function () {
        $plan = Plan::factory()->enterprise()->create();

        expect($plan->name)->toBe('Plano Empresarial')
            ->and($plan->price)->toBe(999.00)
            ->and($plan->max_users)->toBe(100);
    });

    test('preço formatado', function () {
        $plan = Plan::factory()->create(['price' => 299.00]);

        expect($plan->formatted_price)->toBe('R$ 299,00');
    });

    test('tipo em português', function () {
        $plan = Plan::factory()->create(['type' => 'monthly']);

        expect($plan->type_name)->toBe('Mensal');
    });

    test('pode adicionar feature', function () {
        $plan = Plan::factory()->create();
        $plan->addFeature('integração_nfe');

        expect($plan->getFeatures())->toContain('integração_nfe');
    });

    test('pode remover feature', function () {
        $plan = Plan::factory()->create([
            'features' => json_encode(['feature_1', 'feature_2']),
        ]);
        $plan->removeFeature('feature_1');

        expect($plan->getFeatures())->not->toContain('feature_1');
    });

    test('scope active funciona', function () {
        Plan::factory()->inactive()->create();
        Plan::factory()->count(2)->create(['is_active' => true]);

        $result = Plan::active()->get();

        expect($result)->toHaveCount(2);
    });

});
