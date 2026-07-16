<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Lead;
use App\Models\Tenant\LeadActivity;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Services\Tenant\CrmService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['tenancy.database.central_connection' => 'sqlite']);

    $this->user = User::factory()->create(['role' => 'admin']);
    $this->userId = (string) $this->user->id;

    $this->company = Company::factory()->create();
    $this->tenant = Tenant::factory()->active()->create([
        'company_id' => $this->company->id,
        'slug' => 'crm-tenant',
    ]);
    tenancy()->initialize($this->tenant);

    $this->branch = Branch::factory()->create();
    $this->crmService = app(CrmService::class);
});

describe('Lead Management Logic', function () {
    test('can create and update lead', function () {
        $lead = $this->crmService->createLead([
            'branch_id' => $this->branch->id,
            'name' => 'Transportadora Rapido',
            'company_name' => 'Rapido Ltda',
            'email' => 'contato@rapido.com',
            'phone' => '11988887777',
            'source' => 'cold_call',
            'status' => Lead::STATUS_NEW,
            'estimated_value' => 15000.00,
            'assigned_to' => $this->user->id,
            'notes' => 'Frota de 10 caminhoes diesel',
        ], $this->userId);

        expect($lead->id)->not->toBeNull();
        expect($lead->name)->toBe('Transportadora Rapido');
        expect($lead->status)->toBe(Lead::STATUS_NEW);
        expect($lead->estimated_value)->toBe(15000.00);

        // Update lead status
        $updated = $this->crmService->updateLead($lead, [
            'status' => Lead::STATUS_CONTACTED,
            'notes' => 'Contato feito com sucesso',
        ]);

        expect($updated->status)->toBe(Lead::STATUS_CONTACTED);
        expect($updated->notes)->toBe('Contato feito com sucesso');
    });

    test('can create and complete lead activities', function () {
        $lead = Lead::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
        ]);

        $activity = $this->crmService->createActivity($lead, [
            'type' => LeadActivity::TYPE_CALL,
            'title' => 'Ligar para agendar visita',
            'description' => 'Apresentar tabela de precos para oficina',
            'due_date' => now()->addDays(2)->toDateTimeString(),
        ], $this->userId);

        expect($activity->id)->not->toBeNull();
        expect($activity->lead_id)->toBe($lead->id);
        expect($activity->completed_at)->toBeNull();

        // Complete activity
        $completed = $this->crmService->completeActivity($activity);
        expect($completed->completed_at)->not->toBeNull();
    });
});

describe('Lead Conversion Flow', function () {
    test('can convert lead to customer automatically', function () {
        $lead = $this->crmService->createLead([
            'branch_id' => $this->branch->id,
            'name' => 'Frota Sete Voltas',
            'company_name' => 'Sete Voltas Logistica',
            'email' => 'logistica@setevoltas.com',
            'phone' => '11977776666',
            'notes' => 'Gostou do orcamento de revisao de motor',
        ], $this->userId);

        expect($lead->status)->toBe(Lead::STATUS_NEW);
        expect($lead->converted_customer_id)->toBeNull();

        $customer = $this->crmService->convert($lead, $this->userId);

        expect($customer)->toBeInstanceOf(Customer::class);
        expect($customer->name)->toBe('Frota Sete Voltas');
        expect($customer->email)->toBe('logistica@setevoltas.com');
        expect($customer->type)->toBe(Customer::TYPE_COMPANY);
        expect($customer->metadata['company_name'])->toBe('Sete Voltas Logistica');

        // Check lead status updated
        $lead->refresh();
        expect($lead->status)->toBe(Lead::STATUS_CONVERTED);
        expect($lead->converted_customer_id)->toBe($customer->id);

        // Verification activity is registered on lead
        expect($lead->activities()->where('type', LeadActivity::TYPE_NOTE)->exists())->toBeTrue();
    });

    test('cannot convert already converted lead', function () {
        $lead = Lead::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => Lead::STATUS_CONVERTED,
            'created_by' => $this->user->id,
        ]);

        expect(fn() => $this->crmService->convert($lead, $this->userId))
            ->toThrow(ValidationException::class);
    });
});

describe('CRM API Endpoints', function () {
    test('can manage leads through API', function () {
        $url = "http://crm-tenant.prime-erp.local/api/leads";

        // Create
        $response = $this->actingAs($this->user)->postJson($url, [
            'branch_id' => $this->branch->id,
            'name' => 'Transporte Rapido API',
            'company_name' => 'Rapido API Ltda',
            'email' => 'contato@api.com',
            'phone' => '11977778888',
        ]);

        $response->assertStatus(201);
        $leadId = $response->json('id');

        // List
        $responseList = $this->actingAs($this->user)->getJson($url);
        $responseList->assertStatus(200);
        $responseList->assertJsonCount(1);

        // Show
        $responseShow = $this->actingAs($this->user)->getJson("{$url}/{$leadId}");
        $responseShow->assertStatus(200);
        expect($responseShow->json('name'))->toBe('Transporte Rapido API');

        // Update
        $responseUpdate = $this->actingAs($this->user)->putJson("{$url}/{$leadId}", [
            'status' => 'contacted',
        ]);
        $responseUpdate->assertStatus(200);
        expect($responseUpdate->json('status'))->toBe('contacted');

        // Convert
        $responseConvert = $this->actingAs($this->user)->postJson("{$url}/{$leadId}/convert");
        $responseConvert->assertStatus(200);
        $responseConvert->assertJsonStructure([
            'message',
            'customer' => ['id', 'name', 'email', 'phone', 'type', 'status']
        ]);

        // Verify lead is converted
        $lead = Lead::find($leadId);
        expect($lead->status)->toBe(Lead::STATUS_CONVERTED);
    });

    test('can manage lead activities through API', function () {
        $lead = Lead::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->user->id,
        ]);

        $url = "http://crm-tenant.prime-erp.local/api/leads/{$lead->id}/activities";

        // Create activity
        $response = $this->actingAs($this->user)->postJson($url, [
            'type' => 'call',
            'title' => 'Ligacao inicial API',
            'description' => 'Apresentacao inicial',
        ]);

        $response->assertStatus(201);
        $activityId = $response->json('id');

        // List activities
        $responseList = $this->actingAs($this->user)->getJson($url);
        $responseList->assertStatus(200);
        $responseList->assertJsonCount(1);

        // Complete activity
        $responseComplete = $this->actingAs($this->user)->postJson("http://crm-tenant.prime-erp.local/api/activities/{$activityId}/complete");
        $responseComplete->assertStatus(200);
        expect($responseComplete->json('activity.completed_at'))->not->toBeNull();
    });
});
