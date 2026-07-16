<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Branch;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderServiceLine;
use App\Models\Tenant\Service;
use App\Models\Tenant\PaymentTerm;
use App\Models\Tenant\PaymentMethod;
use App\DTOs\Tenant\CreateScheduleDTO;
use App\Services\Tenant\ScheduleService;
use App\Services\Tenant\InvoiceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['tenancy.database.central_connection' => 'sqlite']);
    
    $this->adminUser = User::factory()->create(['role' => 'admin']);
    $this->regularUser = User::factory()->create(['role' => 'user']);
    $this->userId = (string) $this->adminUser->id;

    $this->company = Company::factory()->create();
    $this->tenant = Tenant::factory()->active()->create([
        'company_id' => $this->company->id,
        'slug' => 'settings-tenant',
    ]);
    tenancy()->initialize($this->tenant);

    $this->branch = Branch::factory()->create();
    $this->customer = Customer::factory()->create();
    $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);
    
    $this->scheduleService = app(ScheduleService::class);
    $this->invoiceService = app(InvoiceService::class);
});

describe('Setting Model Helpers', function () {
    test('can set and get string setting', function () {
        Setting::set('company_name', 'ERP Prime Logística', 'string', 'general');
        expect(Setting::get('company_name'))->toBe('ERP Prime Logística');
    });

    test('can set and get boolean setting', function () {
        Setting::set('prevent_double_booking', false);
        expect(Setting::get('prevent_double_booking'))->toBeFalse();

        Setting::set('prevent_double_booking', true);
        expect(Setting::get('prevent_double_booking'))->toBeTrue();
    });

    test('can set and get numeric settings', function () {
        Setting::set('tax_iss_rate', 7.5);
        expect(Setting::get('tax_iss_rate'))->toBe(7.5);

        Setting::set('cnh_expiration_warning_days', 45);
        expect(Setting::get('cnh_expiration_warning_days'))->toBe(45);
    });

    test('can set and get json settings', function () {
        $colors = ['primary' => '#111827', 'secondary' => '#60a5fa'];
        Setting::set('company_colors', $colors);
        expect(Setting::get('company_colors'))->toBe($colors);
    });
});

describe('ScheduleService prevent_double_booking Integration', function () {
    test('prevents double-booking by default or when set to true', function () {
        Setting::set('prevent_double_booking', true);

        $dto1 = new CreateScheduleDTO(
            branch_id: (int) $this->branch->id,
            customer_id: (int) $this->customer->id,
            title: 'Revisão A',
            start_time: '2026-08-01 10:00:00',
            end_time: '2026-08-01 12:00:00',
            vehicle_id: $this->vehicle->id,
            order_of_service_id: null,
            description: 'Troca de óleo',
            assigned_to: 'mecanico_1',
            notes: null,
            metadata: []
        );

        $this->scheduleService->store($dto1, $this->userId);

        $dto2 = new CreateScheduleDTO(
            branch_id: (int) $this->branch->id,
            customer_id: (int) $this->customer->id,
            title: 'Revisão B',
            start_time: '2026-08-01 11:00:00',
            end_time: '2026-08-01 13:00:00',
            vehicle_id: $this->vehicle->id,
            order_of_service_id: null,
            description: 'Alinhamento',
            assigned_to: 'mecanico_1',
            notes: null,
            metadata: []
        );

        expect(fn() => $this->scheduleService->store($dto2, $this->userId))
            ->toThrow(ValidationException::class);
    });

    test('allows double-booking when prevent_double_booking is set to false', function () {
        Setting::set('prevent_double_booking', false);

        $dto1 = new CreateScheduleDTO(
            branch_id: (int) $this->branch->id,
            customer_id: (int) $this->customer->id,
            title: 'Revisão A',
            start_time: '2026-08-01 10:00:00',
            end_time: '2026-08-01 12:00:00',
            vehicle_id: $this->vehicle->id,
            order_of_service_id: null,
            description: 'Troca de óleo',
            assigned_to: 'mecanico_1',
            notes: null,
            metadata: []
        );

        $this->scheduleService->store($dto1, $this->userId);

        $dto2 = new CreateScheduleDTO(
            branch_id: (int) $this->branch->id,
            customer_id: (int) $this->customer->id,
            title: 'Revisão B',
            start_time: '2026-08-01 11:00:00',
            end_time: '2026-08-01 13:00:00',
            vehicle_id: $this->vehicle->id,
            order_of_service_id: null,
            description: 'Alinhamento',
            assigned_to: 'mecanico_1',
            notes: null,
            metadata: []
        );

        $schedule = $this->scheduleService->store($dto2, $this->userId);
        expect($schedule)->not->toBeNull();
    });
});

describe('InvoiceService tax_iss_rate Integration', function () {
    test('calculates custom ISS tax rate from settings', function () {
        $order = OrderOfService::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'vehicle_id' => $this->vehicle->id,
        ]);
        $service = Service::factory()->create();

        OrderServiceLine::factory()->forOrder($order)->forService($service)->create([
            'quantity' => 1,
            'unit_price' => 1000.00,
            'total_price' => 1000.00,
        ]);

        $term = PaymentTerm::factory()->create();
        $method = PaymentMethod::factory()->create();

        $invoice1 = $this->invoiceService->createFromOrder($order, $term->id, $method->id, $this->userId);
        expect($invoice1->tax_amount)->toEqual(50.00);

        Setting::set('tax_iss_rate', 10.0);
        $invoice2 = $this->invoiceService->createFromOrder($order, $term->id, $method->id, $this->userId);
        expect($invoice2->tax_amount)->toEqual(100.00);
    });
});

describe('Settings API Endpoints', function () {
    test('index endpoint returns all settings', function () {
        Setting::set('test_key', 'test_val');
        $url = "http://settings-tenant.prime-erp.local/api/settings";

        $response = $this->actingAs($this->regularUser)->getJson($url);
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'test_key' => [
                'value' => 'test_val',
                'type' => 'string',
                'group' => 'general',
            ]
        ]);
    });

    test('update endpoint allows admin to update settings in bulk', function () {
        $url = "http://settings-tenant.prime-erp.local/api/settings";

        $response1 = $this->actingAs($this->regularUser)->postJson($url, [
            'settings' => [
                ['key' => 'tax_iss_rate', 'value' => 8.0, 'type' => 'float', 'group' => 'financial']
            ]
        ]);
        $response1->assertStatus(403);

        $response2 = $this->actingAs($this->adminUser)->postJson($url, [
            'settings' => [
                ['key' => 'tax_iss_rate', 'value' => 8.0, 'type' => 'float', 'group' => 'financial']
            ]
        ]);
        $response2->assertStatus(200);
        expect(Setting::get('tax_iss_rate'))->toBe(8.0);
    });

    test('logo endpoint uploads logo image', function () {
        Storage::fake('public');
        $url = "http://settings-tenant.prime-erp.local/api/settings/logo";

        // Create a fake file that does not require GD extension to avoid error
        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $response1 = $this->actingAs($this->regularUser)->postJson($url, [
            'logo' => $file
        ]);
        $response1->assertStatus(403);

        $response2 = $this->actingAs($this->adminUser)->postJson($url, [
            'logo' => $file
        ]);
        $response2->assertStatus(200);
        
        $logoUrl = Setting::get('company_logo');
        expect($logoUrl)->not->toBeNull();
        
        $tenantId = tenant('id') ?? 'default';
        $files = Storage::disk('public')->allFiles("tenants/{$tenantId}/logo");
        expect(count($files))->toBe(1);
    });
});
