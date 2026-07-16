<?php

use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\QaTemplate;
use App\Models\Tenant\QaInspection;
use App\Models\Tenant\QaDefect;
use App\Models\Tenant\OrderFeedback;
use App\Models\Tenant\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Quality Management Feature Tests', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'qa-tenant',
        ]);

        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        
        $this->customer = Customer::create([
            'name' => 'QA John Doe Customer',
            'email' => 'qa_john@customer.com',
            'status' => Customer::STATUS_ACTIVE,
        ]);

        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);

        $this->order = OrderOfService::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => OrderOfService::STATUS_IN_PROGRESS, // Set to In Progress so it can be completed
            'created_by' => $this->user->id,
        ]);
    });

    test('can manage QA templates CRUD via API', function () {
        $response = $this->actingAs($this->user)
            ->postJson('http://qa-tenant.prime-erp.local/api/qa-templates', [
                'name' => 'Inspeção de Entrega Básica',
                'description' => 'Checklist básico antes de entregar o veículo',
                'items' => [
                    'Verificar nível do óleo',
                    'Calibrar pneus',
                    'Testar freios',
                ],
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $templateId = $response->json('id');
        expect($response->json('name'))->toBe('Inspeção de Entrega Básica');
        expect($response->json('items'))->toHaveCount(3);

        // Update template
        $responseUpdate = $this->actingAs($this->user)
            ->putJson("http://qa-tenant.prime-erp.local/api/qa-templates/{$templateId}", [
                'name' => 'Inspeção de Entrega Completa',
                'description' => 'Nova descrição',
                'items' => [
                    'Verificar nível do óleo',
                    'Calibrar pneus',
                    'Testar freios',
                    'Limpeza interna',
                ],
                'is_active' => true,
            ]);

        $responseUpdate->assertStatus(200);
        expect($responseUpdate->json('name'))->toBe('Inspeção de Entrega Completa');
        expect($responseUpdate->json('items'))->toHaveCount(4);

        // Delete template
        $responseDelete = $this->actingAs($this->user)
            ->deleteJson("http://qa-tenant.prime-erp.local/api/qa-templates/{$templateId}");
        $responseDelete->assertStatus(204);
    });

    test('can complete inspection, log defect and block/unblock OS completion', function () {
        tenancy()->initialize($this->tenant);

        // 1. Create a checklist template
        $template = QaTemplate::create([
            'name' => 'Inspeção de Freios',
            'items' => ['Checar pastilhas', 'Checar discos'],
            'is_active' => true,
        ]);

        // 2. Start inspection on OS
        $response = $this->actingAs($this->user)
            ->postJson('http://qa-tenant.prime-erp.local/api/qa-inspections', [
                'order_of_service_id' => $this->order->id,
                'qa_template_id' => $template->id,
            ]);

        $response->assertStatus(201);
        $inspectionId = $response->json('id');
        expect($response->json('status'))->toBe(QaInspection::STATUS_PENDING);
        expect($response->json('items_checked'))->toHaveCount(2);

        // 3. Update checklist items (one conforms, one fails)
        $itemsChecked = [
            ['name' => 'Checar pastilhas', 'status' => 'checked', 'notes' => 'Tudo certo'],
            ['name' => 'Checar discos', 'status' => 'failed', 'notes' => 'Discos desgastados'],
        ];

        $responseItems = $this->actingAs($this->user)
            ->postJson("http://qa-tenant.prime-erp.local/api/qa-inspections/{$inspectionId}/items", [
                'items' => $itemsChecked,
            ]);

        if ($responseItems->status() !== 200) {
            dump($responseItems->json());
        }
        $responseItems->assertStatus(200);
        expect($responseItems->json('items_checked.1.status'))->toBe('failed');

        // 4. Mark inspection as FAILED
        $responseComplete = $this->actingAs($this->user)
            ->postJson("http://qa-tenant.prime-erp.local/api/qa-inspections/{$inspectionId}/complete", [
                'status' => QaInspection::STATUS_FAILED,
                'notes' => 'Falha na inspeção de freios devido a discos desgastados.',
            ]);

        $responseComplete->assertStatus(200);
        expect($responseComplete->json('status'))->toBe(QaInspection::STATUS_FAILED);

        // 5. Log defect for this failed inspection
        $responseDefect = $this->actingAs($this->user)
            ->postJson("http://qa-tenant.prime-erp.local/api/qa-inspections/{$inspectionId}/defects", [
                'description' => 'Trocar os discos de freio traseiros',
                'severity' => QaDefect::SEVERITY_HIGH,
            ]);

        $responseDefect->assertStatus(201);
        $defectId = $responseDefect->json('id');
        expect($responseDefect->json('status'))->toBe(QaDefect::STATUS_OPEN);

        // 6. Assert that OS cannot be completed due to unresolved defect
        tenancy()->initialize($this->tenant);
        $this->order->refresh();
        expect($this->order->canComplete())->toBeFalse();

        // Try to complete OS through direct call - should fail or block
        $completed = $this->order->complete((string) $this->user->id);
        expect($completed)->toBeFalse();

        // 7. Resolve the defect
        $responseResolve = $this->actingAs($this->user)
            ->postJson("http://qa-tenant.prime-erp.local/api/qa-defects/{$defectId}/resolve");

        $responseResolve->assertStatus(200);
        expect($responseResolve->json('status'))->toBe(QaDefect::STATUS_RESOLVED);

        // 8. Assert that OS can now be completed successfully
        tenancy()->initialize($this->tenant);
        $this->order->refresh();
        expect($this->order->canComplete())->toBeTrue();

        $completedAfter = $this->order->complete((string) $this->user->id);
        expect($completedAfter)->toBeTrue();
    });

    test('can submit customer feedback and retrieve aggregated NPS metrics', function () {
        tenancy()->initialize($this->tenant);

        // Create 3 orders for different feedback scores to test NPS formula
        $order1 = $this->order;
        
        $order2 = OrderOfService::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => OrderOfService::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);

        $order3 = OrderOfService::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => OrderOfService::STATUS_COMPLETED,
            'created_by' => $this->user->id,
        ]);

        // Submit Promoter feedback (NPS 10)
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("http://qa-tenant.prime-erp.local/api/customer/orders/{$order1->id}/feedback", [
                'rating' => 5,
                'nps_score' => 10,
                'comments' => 'Excelente trabalho!',
            ])->assertStatus(201);

        // Submit Neutral feedback (NPS 8)
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("http://qa-tenant.prime-erp.local/api/customer/orders/{$order2->id}/feedback", [
                'rating' => 4,
                'nps_score' => 8,
                'comments' => 'Bom.',
            ])->assertStatus(201);

        // Submit Detractor feedback (NPS 5)
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("http://qa-tenant.prime-erp.local/api/customer/orders/{$order3->id}/feedback", [
                'rating' => 2,
                'nps_score' => 5,
                'comments' => 'Demorou muito.',
            ])->assertStatus(201);

        // Retrieve Quality and NPS metrics
        $responseMetrics = $this->actingAs($this->user)
            ->getJson('http://qa-tenant.prime-erp.local/api/quality/metrics');

        $responseMetrics->assertStatus(200);
        
        // NPS calculation: 1 Promoter (33.33%), 1 Neutral (33.33%), 1 Detractor (33.33%)
        // NPS Score = Promoters% - Detractors% = 33.33% - 33.33% = 0.0%
        expect((float)$responseMetrics->json('customer_feedback.nps_score'))->toBe(0.0);
        expect((float)$responseMetrics->json('customer_feedback.average_rating'))->toBe(3.67);
        expect($responseMetrics->json('customer_feedback.total_responses'))->toBe(3);
    });

    test('can issue warranty for Order of Service', function () {
        $response = $this->actingAs($this->user)
            ->postJson("http://qa-tenant.prime-erp.local/api/orders/{$this->order->id}/warranty", [
                'type' => 'parts',
                'duration_days' => 90,
                'terms' => 'Garantia limitada a defeitos de fabricação das pastilhas de freio.',
            ]);

        $response->assertStatus(201);
        expect($response->json('type'))->toBe('parts');
        expect($response->json('duration_days'))->toBe(90);
        expect(substr($response->json('start_date'), 0, 10))->toBe(now()->toDateString());
        expect(substr($response->json('end_date'), 0, 10))->toBe(now()->addDays(90)->toDateString());
        expect($response->json('status'))->toBe('active');
    });
});
