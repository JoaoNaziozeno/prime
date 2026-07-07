<?php

use App\Models\Master\Subscription;
use App\Models\Master\Company;
use App\Models\Master\Plan;
use App\Models\Master\Tenant;
use Database\Factories\Master\SubscriptionFactory;
use Database\Factories\Master\TenantFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Subscription Model', function () {

    test('pode criar assinatura', function () {
        $subscription = Subscription::factory()->create();

        expect($subscription)->toBeInstanceOf(Subscription::class)
            ->and($subscription->status)->toBe('active');
    });

    test('assinatura possui empresa e plano', function () {
        $subscription = Subscription::factory()->create();

        expect($subscription->company)->toBeInstanceOf(Company::class)
            ->and($subscription->plan)->toBeInstanceOf(Plan::class);
    });

    test('pode criar assinatura em trial', function () {
        $subscription = Subscription::factory()->onTrial()->create();

        expect($subscription->is_on_trial)->toBeTrue();
    });

    test('pode criar assinatura vencendo em breve', function () {
        $subscription = Subscription::factory()->renewingSoon()->create();

        expect($subscription->isRenewingSoon())->toBeTrue();
    });

    test('pode ativar assinatura', function () {
        $subscription = Subscription::factory()->paused()->create();
        $subscription->activate();

        expect($subscription->status)->toBe('active')
            ->and($subscription->isActive())->toBeTrue();
    });

    test('pode pausar assinatura', function () {
        $subscription = Subscription::factory()->active()->create();
        $subscription->pause();

        expect($subscription->status)->toBe('paused');
    });

    test('pode cancelar assinatura', function () {
        $subscription = Subscription::factory()->active()->create();
        $subscription->cancel();

        expect($subscription->status)->toBe('cancelled')
            ->and($subscription->cancelled_at)->not->toBeNull();
    });

    test('pode renovar assinatura', function () {
        $plan = Plan::factory()->create(['billing_cycle_days' => 30]);
        $subscription = Subscription::factory()
            ->active()
            ->create([
                'plan_id' => $plan->id,
                'renews_at' => now(),
            ]);

        $oldDate = $subscription->renews_at;
        $subscription->renew();

        expect($subscription->renews_at)->not->equals($oldDate)
            ->and($subscription->renews_at->diffInDays($oldDate))->toBe(30);
    });

    test('pode criar assinatura com boleto', function () {
        $subscription = Subscription::factory()->withBoleto()->create();

        expect($subscription->payment_method)->toBe('boleto');
    });

    test('pode criar assinatura com pix', function () {
        $subscription = Subscription::factory()->withPix()->create();

        expect($subscription->payment_method)->toBe('pix');
    });

    test('montante formatado', function () {
        $subscription = Subscription::factory()->create(['current_amount' => 299.00]);

        expect($subscription->formatted_amount)->toBe('R$ 299,00');
    });

    test('status em português', function () {
        $subscription = Subscription::factory()->create(['status' => 'active']);

        expect($subscription->status_name)->toBe('Ativa');
    });

    test('scope active funciona', function () {
        Subscription::factory()->cancelled()->create();
        Subscription::factory()->count(2)->active()->create();

        $result = Subscription::active()->get();

        expect($result)->toHaveCount(2);
    });

    test('scope renewingSoon funciona', function () {
        Subscription::factory()->active()->create();
        Subscription::factory()->renewingSoon()->count(2)->create();

        $result = Subscription::renewingSoon()->get();

        expect($result)->toHaveCount(2);
    });

});

describe('Tenant Model', function () {

    test('pode criar tenant', function () {
        $tenant = Tenant::factory()->create();

        expect($tenant)->toBeInstanceOf(Tenant::class)
            ->and($tenant->id)->toBeUuid();
    });

    test('tenant possui empresa', function () {
        $tenant = Tenant::factory()->create();

        expect($tenant->company)->toBeInstanceOf(Company::class);
    });

    test('pode criar tenant ativo', function () {
        $tenant = Tenant::factory()->active()->create();

        expect($tenant->status)->toBe('active')
            ->and($tenant->is_active)->toBeTrue();
    });

    test('pode criar tenant em setup', function () {
        $tenant = Tenant::factory()->setup()->create();

        expect($tenant->status)->toBe('setup')
            ->and($tenant->is_in_setup)->toBeTrue();
    });

    test('pode ativar tenant', function () {
        $tenant = Tenant::factory()->setup()->create();
        $tenant->activate();

        expect($tenant->status)->toBe('active')
            ->and($tenant->activated_at)->not->toBeNull();
    });

    test('pode pausar tenant', function () {
        $tenant = Tenant::factory()->active()->create();
        $tenant->pause();

        expect($tenant->status)->toBe('paused');
    });

    test('pode marcar como deletado', function () {
        $tenant = Tenant::factory()->active()->create();
        $tenant->markAsDeleted();

        expect($tenant->status)->toBe('deleted');
    });

    test('pode obter e setar configurações', function () {
        $tenant = Tenant::factory()->create();
        
        $tenant->setSetting('lang', 'en_US');
        $tenant->save();

        expect($tenant->getSetting('lang'))->toBe('en_US');
    });

    test('pode gerar slug único', function () {
        $company = Company::factory()->create(['name' => 'Empresa Teste']);

        $slug = Tenant::generateSlug($company);

        expect($slug)->toBe('empresa-teste');
    });

    test('pode obter conexão config', function () {
        $tenant = Tenant::factory()->create([
            'database_name' => 'prime_tenant_test',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_driver' => 'mysql',
        ]);

        $config = $tenant->getConnectionConfig();

        expect($config['driver'])->toBe('mysql')
            ->and($config['database'])->toBe('prime_tenant_test')
            ->and($config['host'])->toBe('127.0.0.1');
    });

    test('pode criar tenant com postgres', function () {
        $tenant = Tenant::factory()->withPostgres()->create();

        expect($tenant->db_driver)->toBe('pgsql')
            ->and($tenant->db_port)->toBe(5432);
    });

    test('status em português', function () {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        expect($tenant->status_name)->toBe('Ativo');
    });

    test('scope active funciona', function () {
        Tenant::factory()->setup()->create();
        Tenant::factory()->count(2)->active()->create();

        $result = Tenant::active()->get();

        expect($result)->toHaveCount(2);
    });

});
