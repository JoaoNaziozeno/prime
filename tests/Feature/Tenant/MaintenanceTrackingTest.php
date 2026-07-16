<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Branch;
use App\Models\Tenant\PreventiveRule;
use App\Models\Tenant\MaintenanceLog;
use App\Models\Tenant\VehiclePreventiveRuleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Maintenance Tracking Feature Tests', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'maint-tenant',
        ]);

        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        $this->vehicle = Vehicle::factory()->create([
            'branch_id' => $this->branch->id,
            'odometer' => 1000,
        ]);
    });

    test('can manage preventive rules CRUD via API', function () {
        // Create rule
        $response = $this->actingAs($this->user)
            ->postJson('http://maint-tenant.prime-erp.local/api/preventive-rules', [
                'name' => 'Troca de Óleo de Motor',
                'description' => 'Troca recomendada a cada 10.000 km ou 6 meses',
                'interval_kms' => 10000,
                'interval_days' => 180,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $ruleId = $response->json('id');
        expect($response->json('name'))->toBe('Troca de Óleo de Motor');

        // Read rule
        $responseRead = $this->actingAs($this->user)
            ->getJson("http://maint-tenant.prime-erp.local/api/preventive-rules/{$ruleId}");
        $responseRead->assertStatus(200);
        expect($responseRead->json('interval_kms'))->toBe(10000);

        // Update rule
        $responseUpdate = $this->actingAs($this->user)
            ->putJson("http://maint-tenant.prime-erp.local/api/preventive-rules/{$ruleId}", [
                'name' => 'Troca de Óleo de Motor - Modificado',
                'description' => 'Nova descrição',
                'interval_kms' => 8000,
                'interval_days' => 120,
                'is_active' => false,
            ]);
        $responseUpdate->assertStatus(200);
        expect($responseUpdate->json('interval_kms'))->toBe(8000);
        expect($responseUpdate->json('is_active'))->toBe(false);

        // Delete rule
        $responseDelete = $this->actingAs($this->user)
            ->deleteJson("http://maint-tenant.prime-erp.local/api/preventive-rules/{$ruleId}");
        $responseDelete->assertStatus(204);

        tenancy()->initialize($this->tenant);
        expect(PreventiveRule::find($ruleId))->toBeNull();
    });

    test('can schedule and complete maintenance log', function () {
        tenancy()->initialize($this->tenant);
        $rule = PreventiveRule::create([
            'name' => 'Alinhamento & Balanceamento',
            'interval_kms' => 5000,
            'interval_days' => 90,
        ]);

        // Schedule maintenance log
        $response = $this->actingAs($this->user)
            ->postJson('http://maint-tenant.prime-erp.local/api/maintenance-logs', [
                'vehicle_id' => $this->vehicle->id,
                'preventive_rule_id' => $rule->id,
                'title' => 'Alinhamento Preventivo',
                'description' => 'Realizar alinhamento 3D',
                'type' => MaintenanceLog::TYPE_PREVENTIVE,
                'scheduled_date' => now()->addDays(5)->toDateString(),
            ]);

        $response->assertStatus(201);
        $logId = $response->json('id');
        expect($response->json('status'))->toBe(MaintenanceLog::STATUS_SCHEDULED);

        // Complete maintenance log
        $responseComplete = $this->actingAs($this->user)
            ->postJson("http://maint-tenant.prime-erp.local/api/maintenance-logs/{$logId}/complete", [
                'odometer' => 1500,
                'cost' => 150.00,
            ]);

        $responseComplete->assertStatus(200);
        expect($responseComplete->json('maintenance_log.status'))->toBe(MaintenanceLog::STATUS_COMPLETED);
        expect((float)$responseComplete->json('maintenance_log.cost'))->toBe(150.00);

        // Verify vehicle odometer was updated
        tenancy()->initialize($this->tenant);
        $this->vehicle->refresh();
        expect($this->vehicle->odometer)->toBe(1500);

        // Verify next due calculation was executed
        $status = VehiclePreventiveRuleStatus::where('vehicle_id', $this->vehicle->id)
            ->where('preventive_rule_id', $rule->id)
            ->first();

        expect($status)->not->toBeNull();
        expect($status->last_performed_kms)->toBe(1500);
        expect($status->next_due_kms)->toBe(6500); // 1500 + 5000
        expect($status->next_due_date->toDateString())->toBe(now()->addDays(90)->toDateString());
    });

    test('can retrieve due alerts when thresholds are met', function () {
        tenancy()->initialize($this->tenant);
        
        // Create rule
        $rule = PreventiveRule::create([
            'name' => 'Filtro de Combustível',
            'interval_kms' => 5000,
            'interval_days' => 60,
        ]);

        // Complete log at odometer 1000, today
        $log = MaintenanceLog::create([
            'vehicle_id' => $this->vehicle->id,
            'preventive_rule_id' => $rule->id,
            'title' => 'Filtro Teste',
            'type' => MaintenanceLog::TYPE_PREVENTIVE,
            'status' => MaintenanceLog::STATUS_SCHEDULED,
            'scheduled_date' => now(),
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->postJson("http://maint-tenant.prime-erp.local/api/maintenance-logs/{$log->id}/complete", [
                'odometer' => 1000,
                'cost' => 80.00,
            ]);

        // At odometer 1000, next due is 6000. Vehicle is currently at 1000. 
        // 6000 - 1000 = 5000 remaining. Should not alert.
        $responseAlertsEmpty = $this->actingAs($this->user)
            ->getJson("http://maint-tenant.prime-erp.local/api/vehicles/{$this->vehicle->id}/maintenance-status");
        $responseAlertsEmpty->assertStatus(200);
        expect($responseAlertsEmpty->json('alerts'))->toBeEmpty();

        // Update vehicle odometer to 5200 (within 1000km of next_due 6000)
        tenancy()->initialize($this->tenant);
        $this->vehicle->update(['odometer' => 5200]);

        $responseAlertsFilled = $this->actingAs($this->user)
            ->getJson("http://maint-tenant.prime-erp.local/api/vehicles/{$this->vehicle->id}/maintenance-status");
        $responseAlertsFilled->assertStatus(200);
        expect($responseAlertsFilled->json('alerts'))->toHaveCount(1);
        expect($responseAlertsFilled->json('alerts.0.kms_remaining'))->toBe(800);
        expect($responseAlertsFilled->json('alerts.0.reason'))->toBe('kms');
    });

    test('can retrieve cost analysis aggregated values', function () {
        tenancy()->initialize($this->tenant);

        // Record a corrective log
        MaintenanceLog::create([
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Troca de Farol',
            'type' => MaintenanceLog::TYPE_CORRECTIVE,
            'status' => MaintenanceLog::STATUS_COMPLETED,
            'scheduled_date' => now(),
            'completed_date' => now(),
            'cost' => 300.00,
            'created_by' => $this->user->id,
        ]);

        // Record a preventive log
        MaintenanceLog::create([
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Revisão Básica',
            'type' => MaintenanceLog::TYPE_PREVENTIVE,
            'status' => MaintenanceLog::STATUS_COMPLETED,
            'scheduled_date' => now(),
            'completed_date' => now(),
            'cost' => 200.00,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("http://maint-tenant.prime-erp.local/api/vehicles/{$this->vehicle->id}/maintenance-cost-analysis");

        $response->assertStatus(200);
        expect((float)$response->json('cost_analysis.total_cost'))->toBe(500.00);
        expect((float)$response->json('cost_analysis.average_cost'))->toBe(250.00);
        expect((float)$response->json('cost_analysis.by_type.corrective'))->toBe(300.00);
        expect((float)$response->json('cost_analysis.by_type.preventive'))->toBe(200.00);
    });
});
