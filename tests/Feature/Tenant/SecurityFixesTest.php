<?php

use App\Http\Controllers\API\Master\AuthController;
use App\Models\Master\Company;
use App\Models\Master\Tenant;
use App\Models\Master\User;
use App\Models\Tenant\Role;
use App\Models\Tenant\Setting;
use App\Services\Master\TotpService;
use Illuminate\Http\Request;

describe('RBAC endpoints require an administrator', function () {

    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'security-fixes-rbac',
        ]);
        tenancy()->initialize($this->tenant);

        $this->member = User::factory()->create(['role' => 'user']);
        $this->member->companies()->attach($this->company->id, ['role' => 'member']);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->companies()->attach($this->company->id, ['role' => 'admin']);

        Role::create(['name' => 'Gerente']);
    });

    test('regular user cannot create roles', function () {
        $url = 'http://security-fixes-rbac.prime-erp.local/api/rbac/roles';

        $response = $this->actingAs($this->member)->postJson($url, [
            'name' => 'Novo Papel',
            'permissions' => [],
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Não autorizado.']);
    });

    test('regular user cannot assign a role to themselves', function () {
        $url = 'http://security-fixes-rbac.prime-erp.local/api/rbac/assign';

        $response = $this->actingAs($this->member)->postJson($url, [
            'user_id' => $this->member->id,
            'role_name' => 'Gerente',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Não autorizado.']);
    });

    test('admin cannot change roles of a user from another company', function () {
        $otherCompany = Company::factory()->create();
        $outsider = User::factory()->create(['role' => 'user']);
        $outsider->companies()->attach($otherCompany->id, ['role' => 'member']);

        $url = 'http://security-fixes-rbac.prime-erp.local/api/rbac/assign';

        $this->actingAs($this->admin)->postJson($url, [
            'user_id' => $outsider->id,
            'role_name' => 'Gerente',
        ])->assertStatus(404);
    });

    test('admin can assign a role to a user of the same company', function () {
        $url = 'http://security-fixes-rbac.prime-erp.local/api/rbac/assign';

        $this->actingAs($this->admin)->postJson($url, [
            'user_id' => $this->member->id,
            'role_name' => 'Gerente',
        ])->assertStatus(200);
    });
});

describe('Settings API does not expose secrets', function () {

    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'security-fixes-settings',
        ]);
        tenancy()->initialize($this->tenant);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->companies()->attach($this->company->id, ['role' => 'admin']);
    });

    test('secret values are masked in the index response', function () {
        Setting::set('stripe_secret_key', 'sk_live_super_secret');
        Setting::set('mail_password', 'smtp-password');
        Setting::set('company_name', 'Oficina Diesel');

        $url = 'http://security-fixes-settings.prime-erp.local/api/settings';
        $response = $this->actingAs($this->admin)->getJson($url);

        $response->assertStatus(200);
        expect($response->json('stripe_secret_key.value'))->toBe('********');
        expect($response->json('mail_password.value'))->toBe('********');
        expect($response->json('company_name.value'))->toBe('Oficina Diesel');
        expect($response->getContent())->not->toContain('sk_live_super_secret');
        expect($response->getContent())->not->toContain('smtp-password');
    });

    test('sending the mask back keeps the stored secret unchanged', function () {
        Setting::set('stripe_secret_key', 'sk_live_super_secret');

        $url = 'http://security-fixes-settings.prime-erp.local/api/settings';
        $this->actingAs($this->admin)->postJson($url, [
            'settings' => [
                ['key' => 'stripe_secret_key', 'value' => '********'],
            ],
        ])->assertStatus(200);

        expect(Setting::get('stripe_secret_key'))->toBe('sk_live_super_secret');
    });
});

describe('Two-factor authentication is enforced at login', function () {

    beforeEach(function () {
        $this->totp = app(TotpService::class);
        $this->secret = $this->totp->generateSecret();
        $this->recoveryCodes = ['recovery-one', 'recovery-two'];

        $this->user = User::factory()->create([
            'role' => 'user',
            'two_factor_secret' => $this->secret,
            'two_factor_recovery_codes' => encrypt(json_encode($this->recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->login = function (array $extra = []) {
            $request = Request::create('/api/auth/login', 'POST', array_merge([
                'email' => $this->user->email,
                'password' => 'password',
            ], $extra));

            return app(AuthController::class)->login($request);
        };

        $this->currentCode = function () {
            $method = new ReflectionMethod($this->totp, 'calculateCode');

            return $method->invoke($this->totp, $this->secret, (int) floor(time() / 30));
        };
    });

    test('login without a 2FA code does not issue a token', function () {
        $response = ($this->login)();

        expect($response->getStatusCode())->toBe(422);
        expect($response->getData(true))->toMatchArray(['two_factor_required' => true]);
        expect($response->getData(true))->not->toHaveKey('access_token');
    });

    test('login with a wrong 2FA code is rejected', function () {
        $response = ($this->login)(['two_factor_code' => '000000']);

        expect($response->getStatusCode())->toBe(401);
        expect($response->getData(true))->not->toHaveKey('access_token');
    });

    test('login with a valid 2FA code issues a token', function () {
        $response = ($this->login)(['two_factor_code' => ($this->currentCode)()]);

        expect($response->getStatusCode())->toBe(200);
        expect($response->getData(true))->toHaveKey('access_token');
    });

    test('a recovery code works once and is then consumed', function () {
        $first = ($this->login)(['recovery_code' => 'recovery-one']);
        expect($first->getStatusCode())->toBe(200);

        $second = ($this->login)(['recovery_code' => 'recovery-one']);
        expect($second->getStatusCode())->toBe(401);

        $remaining = json_decode(decrypt($this->user->refresh()->two_factor_recovery_codes), true);
        expect($remaining)->toBe(['recovery-two']);
    });

    test('users without 2FA can still log in with email and password only', function () {
        $plain = User::factory()->create(['role' => 'user']);

        $response = app(AuthController::class)->login(
            Request::create('/api/auth/login', 'POST', [
                'email' => $plain->email,
                'password' => 'password',
            ])
        );

        expect($response->getStatusCode())->toBe(200);
    });
});
