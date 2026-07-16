<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Services\Tenant\Gateways\TwilioSmsGateway;
use App\Contracts\Tenant\SmsGatewayInterface;
use App\Services\Tenant\DocumentService;
use App\DTOs\Tenant\CreateDocumentDTO;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    config(['tenancy.database.central_connection' => 'sqlite']);
    
    $this->user = User::factory()->superAdmin()->create();
    $this->company = Company::factory()->create();
    $this->tenant = Tenant::factory()->active()->create([
        'company_id' => $this->company->id,
        'slug' => 'integrations-tenant',
    ]);
    tenancy()->initialize($this->tenant);
});

describe('Dynamic SMTP Email Integration', function () {
    test('dynamic mail settings override mail config at runtime', function () {
        // Set dynamic SMTP settings
        Setting::set('mail_host', 'smtp.dynamic-tenant.io');
        Setting::set('mail_port', 587);
        Setting::set('mail_username', 'tenant-user');
        Setting::set('mail_password', 'secret-pass');
        Setting::set('mail_encryption', 'tls');
        Setting::set('mail_from_address', 'office@tenant.com');
        Setting::set('mail_from_name', 'Tenant Workshop');

        // Simulate request hitting the API where middleware ConfigureTenantIntegrations would execute
        $request = Request::create('http://integrations-tenant.prime-erp.local/api/status', 'GET');
        $middleware = new \App\Http\Middleware\ConfigureTenantIntegrations();
        
        $middleware->handle($request, function () {
            // Assert configs are overwritten dynamically
            expect(config('mail.default'))->toBe('smtp');
            expect(config('mail.mailers.smtp.host'))->toBe('smtp.dynamic-tenant.io');
            expect(config('mail.mailers.smtp.port'))->toBe(587);
            expect(config('mail.mailers.smtp.username'))->toBe('tenant-user');
            expect(config('mail.mailers.smtp.password'))->toBe('secret-pass');
            expect(config('mail.mailers.smtp.encryption'))->toBe('tls');
            expect(config('mail.from.address'))->toBe('office@tenant.com');
            expect(config('mail.from.name'))->toBe('Tenant Workshop');

            return response('OK');
        });
    });
});

describe('Twilio SMS Gateway Integration', function () {
    test('uses TwilioSmsGateway when sms_provider setting is twilio', function () {
        Setting::set('sms_provider', 'twilio');
        
        // Resolve gateway
        $gateway = app(SmsGatewayInterface::class);
        expect($gateway)->toBeInstanceOf(TwilioSmsGateway::class);
    });

    test('falls back to MockSmsGateway when sms_provider setting is not twilio', function () {
        Setting::set('sms_provider', 'mock');
        
        $gateway = app(SmsGatewayInterface::class);
        expect($gateway)->toBeInstanceOf(\App\Services\Tenant\Gateways\MockSmsGateway::class);
    });

    test('TwilioSmsGateway makes proper HTTP request to Twilio API and authenticates', function () {
        // Configure credentials in config
        config([
            'services.twilio.sid' => 'AC_TEST_SID',
            'services.twilio.auth_token' => 'TEST_TOKEN',
            'services.twilio.from' => '+12345678',
        ]);

        Http::fake([
            'https://api.twilio.com/*' => Http::response(['sid' => 'SM_MOCK_SID'], 201),
        ]);

        $gateway = new TwilioSmsGateway();
        $result = $gateway->send('+5511999999999', 'Hello World');

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_TEST_SID/Messages.json'
                && $request->isForm()
                && $request->data()['To'] === '+5511999999999'
                && $request->data()['From'] === '+12345678'
                && $request->data()['Body'] === 'Hello World'
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('AC_TEST_SID:TEST_TOKEN'));
        });
    });

    test('TwilioSmsGateway logs error and returns false on failure', function () {
        config([
            'services.twilio.sid' => 'AC_TEST_SID',
            'services.twilio.auth_token' => 'TEST_TOKEN',
            'services.twilio.from' => '+12345678',
        ]);

        Http::fake([
            'https://api.twilio.com/*' => Http::response('Unauthorized', 401),
        ]);

        $gateway = new TwilioSmsGateway();
        $result = $gateway->send('+5511999999999', 'Hello World');

        expect($result)->toBeFalse();
    });
});

describe('Dynamic Stripe Credentials', function () {
    test('stripe secret key gets overridden dynamically by tenant settings', function () {
        Setting::set('stripe_secret_key', 'sk_tenant_test_key');
        Setting::set('stripe_webhook_secret', 'whsec_tenant_secret');

        $request = Request::create('http://integrations-tenant.prime-erp.local/api/status', 'GET');
        $middleware = new \App\Http\Middleware\ConfigureTenantIntegrations();
        
        $middleware->handle($request, function () {
            expect(config('services.stripe.secret'))->toBe('sk_tenant_test_key');
            expect(config('services.stripe.webhook_secret'))->toBe('whsec_tenant_secret');
            return response('OK');
        });
    });
});

describe('Dynamic S3/MinIO Storage Configuration', function () {
    test('s3 storage disk default swaps and credentials configure dynamically', function () {
        // Set tenant S3 settings
        Setting::set('s3_bucket', 'tenant-bucket-name');
        Setting::set('s3_key', 'AKIA_KEY');
        Setting::set('s3_secret', 'SECRET_KEY_123');
        Setting::set('s3_region', 'sa-east-1');
        Setting::set('s3_endpoint', 'https://minio.tenant.io');
        Setting::set('s3_url', 'https://minio.tenant.io/tenant-bucket-name');

        $request = Request::create('http://integrations-tenant.prime-erp.local/api/status', 'GET');
        $middleware = new \App\Http\Middleware\ConfigureTenantIntegrations();
        
        $middleware->handle($request, function () {
            // Filesystem default disk should be updated to s3
            expect(config('filesystems.default'))->toBe('s3');
            expect(config('filesystems.disks.s3.bucket'))->toBe('tenant-bucket-name');
            expect(config('filesystems.disks.s3.key'))->toBe('AKIA_KEY');
            expect(config('filesystems.disks.s3.secret'))->toBe('SECRET_KEY_123');
            expect(config('filesystems.disks.s3.region'))->toBe('sa-east-1');
            expect(config('filesystems.disks.s3.endpoint'))->toBe('https://minio.tenant.io');
            expect(config('filesystems.disks.s3.url'))->toBe('https://minio.tenant.io/tenant-bucket-name');

            return response('OK');
        });
    });

    test('DocumentService resolves disk dynamically from filesystems config', function () {
        // 1. Initially default filesystem is local, should store in local
        Storage::fake('local');
        Storage::fake('s3');

        $branch = \App\Models\Tenant\Branch::factory()->create();
        $customer = \App\Models\Tenant\Customer::factory()->create();

        $dto = CreateDocumentDTO::fromRequest([
            'branch_id' => $branch->id,
            'documentable_type' => \App\Models\Tenant\Customer::class,
            'documentable_id' => $customer->id,
            'title' => 'CNH.pdf',
            'description' => 'Driver License Copy',
            'document_type' => 'cnh',
            'expires_at' => now()->addYear()->toDateTimeString(),
        ]);

        $file = UploadedFile::fake()->create('CNH.pdf', 100, 'application/pdf');
        
        $service = new DocumentService();
        $doc = $service->store($dto, $file, $this->user->id);

        expect($doc->file_path)->toContain('documents/cnh');
        Storage::disk('local')->assertExists($doc->file_path);

        // 2. Change default filesystem config dynamically to s3 (simulate middleware)
        config(['filesystems.default' => 's3']);

        $fileS3 = UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf');
        $dtoS3 = CreateDocumentDTO::fromRequest([
            'branch_id' => $branch->id,
            'documentable_type' => \App\Models\Tenant\Customer::class,
            'documentable_id' => $customer->id,
            'title' => 'contract.pdf',
            'description' => 'Service Contract',
            'document_type' => 'other',
            'expires_at' => null,
        ]);

        $docS3 = $service->store($dtoS3, $fileS3, $this->user->id);
        Storage::disk('s3')->assertExists($docS3->file_path);
    });
});
