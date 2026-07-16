<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Setting;
use App\Models\Tenant\CustomReport;
use App\Jobs\Tenant\RenderCustomReportJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Http\UploadedFile;

describe('Performance and Load Feature Tests', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'perf-tenant',
        ]);

        tenancy()->initialize($this->tenant);
        
        Cache::clear();
    });

    afterEach(function () {
        Cache::clear();
    });

    test('settings are cached and bust properly on update', function () {
        tenancy()->initialize($this->tenant);

        // Define setting
        Setting::set('company_name', 'Acme Test Engine', 'string');

        // Clear query log
        DB::connection('tenant')->flushQueryLog();
        DB::connection('tenant')->enableQueryLog();

        // 1st retrieval - database query
        $val1 = Setting::get('company_name');
        expect($val1)->toBe('Acme Test Engine');

        $queriesAfter1st = count(DB::connection('tenant')->getQueryLog());
        expect($queriesAfter1st)->toBe(1);

        // 2nd, 3rd, 4th retrieval - Cache hits (0 queries)
        $val2 = Setting::get('company_name');
        $val3 = Setting::get('company_name');
        expect($val2)->toBe('Acme Test Engine');
        expect($val3)->toBe('Acme Test Engine');

        $queriesAfterMultiple = count(DB::connection('tenant')->getQueryLog());
        expect($queriesAfterMultiple)->toBe(1); // Still only 1 query!

        // Update setting (busts cache)
        Setting::set('company_name', 'Acme New Engine', 'string');

        // 5th retrieval after update - database query
        $val4 = Setting::get('company_name');
        expect($val4)->toBe('Acme New Engine');

        $queriesFinal = count(DB::connection('tenant')->getQueryLog());
        expect($queriesFinal)->toBeGreaterThan(1);
    });

    test('can dispatch report generation to background job queue', function () {
        tenancy()->initialize($this->tenant);

        $report = CustomReport::create([
            'name' => 'Large OS Report',
            'model_type' => CustomReport::TYPE_ORDERS,
            'columns' => ['id', 'status'],
            'filters' => [],
            'created_by' => $this->user->id,
        ]);

        // Request queued render via API
        $response = $this->actingAs($this->user)
            ->postJson("http://perf-tenant.prime-erp.local/api/custom-reports/{$report->id}/queue");

        $response->assertStatus(202);
        expect($response->json('message'))->toContain('enfileirada');

        // Run the job handler directly to simulate queue execution
        $job = new RenderCustomReportJob($this->tenant, $report, []);
        app()->call([$job, 'handle']);

        // Check if file is stored
        $status = Cache::get("report_{$report->id}_last_rendered");
        expect($status['status'])->toBe('completed');
        expect(Storage::disk('local')->exists($status['path']))->toBeTrue();

        // Fetch queue status via API
        $responseStatus = $this->actingAs($this->user)
            ->getJson("http://perf-tenant.prime-erp.local/api/custom-reports/{$report->id}/queue/status");
        $responseStatus->assertStatus(200);
        expect($responseStatus->json('status'))->toBe('completed');

        // Download queued report via API
        $responseDownload = $this->actingAs($this->user)
            ->getJson("http://perf-tenant.prime-erp.local/api/custom-reports/{$report->id}/queue/download");
        $responseDownload->assertStatus(200);
        $responseDownload->assertHeader('Content-Disposition', 'attachment; filename="report_queued_' . $report->id . '.csv"');
    });

    test('logo view endpoint serves file with CDN caching headers', function () {
        Storage::fake('public');
        tenancy()->initialize($this->tenant);

        // Upload logo
        $logoFile = UploadedFile::fake()->create('my_logo.png', 100, 'image/png');
        
        $responseUpload = $this->actingAs($this->user)
            ->postJson('http://perf-tenant.prime-erp.local/api/settings/logo', [
                'logo' => $logoFile,
            ]);

        $responseUpload->assertStatus(200);
        $logoUrl = $responseUpload->json('logo_url');

        // Fetch logo file with Cache headers
        $responseView = $this->actingAs($this->user)
            ->getJson('http://perf-tenant.prime-erp.local/api/settings/logo/view');

        $responseView->assertStatus(200);
        $cc = $responseView->headers->get('Cache-Control');
        expect($cc)->toContain('public')
            ->toContain('max-age=31536000')
            ->toContain('immutable');
    });
});
