<?php

use App\Models\Tenant\Schedule;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Vehicle;
use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Services\Tenant\ScheduleService;
use App\DTOs\Tenant\CreateScheduleDTO;
use App\DTOs\Tenant\UpdateScheduleDTO;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

describe('Scheduling Service & Conflict Prevention', function () {

    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'scheduling-logic-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);

        $this->scheduleService = app(ScheduleService::class);
        $this->userId = fake()->uuid();
    });

    test('creates schedule successfully when no conflicts exist', function () {
        $dto = new CreateScheduleDTO(
            branch_id: $this->branch->id,
            customer_id: $this->customer->id,
            title: 'Revisão Geral',
            start_time: now()->addDay()->setHour(10)->toDateString() . ' 10:00:00',
            end_time: now()->addDay()->setHour(10)->toDateString() . ' 12:00:00',
            vehicle_id: $this->vehicle->id,
            assigned_to: fake()->uuid(),
        );

        $schedule = $this->scheduleService->store($dto, $this->userId);

        expect($schedule)->toBeInstanceOf(Schedule::class);
        expect($schedule->title)->toBe('Revisão Geral');
        expect($schedule->status)->toBe(Schedule::STATUS_SCHEDULED);
    });

    test('throws validation exception when start time is after or equal to end time', function () {
        $dto = new CreateScheduleDTO(
            branch_id: $this->branch->id,
            customer_id: $this->customer->id,
            title: 'Revisão Geral',
            start_time: now()->addDay()->setHour(12)->toDateString() . ' 12:00:00',
            end_time: now()->addDay()->setHour(12)->toDateString() . ' 10:00:00',
            vehicle_id: $this->vehicle->id,
        );

        $this->expectException(ValidationException::class);
        $this->scheduleService->store($dto, $this->userId);
    });

    test('prevents double-booking for the same worker', function () {
        $assignedTo = fake()->uuid();

        // Existing schedule from 10:00 to 12:00
        Schedule::factory()->create([
            'branch_id' => $this->branch->id,
            'start_time' => now()->addDay()->setHour(10)->toDateString() . ' 10:00:00',
            'end_time' => now()->addDay()->setHour(10)->toDateString() . ' 12:00:00',
            'assigned_to' => $assignedTo,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        // Trying to book same worker from 11:00 to 13:00 (overlaps!)
        $dto = new CreateScheduleDTO(
            branch_id: $this->branch->id,
            customer_id: $this->customer->id,
            title: 'Troca de Óleo',
            start_time: now()->addDay()->setHour(11)->toDateString() . ' 11:00:00',
            end_time: now()->addDay()->setHour(11)->toDateString() . ' 13:00:00',
            assigned_to: $assignedTo,
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O funcionário selecionado já possui um agendamento conflitante neste horário.');

        $this->scheduleService->store($dto, $this->userId);
    });

    test('prevents double-booking for the same vehicle', function () {
        // Existing schedule from 14:00 to 16:00
        Schedule::factory()->create([
            'branch_id' => $this->branch->id,
            'start_time' => now()->addDay()->setHour(14)->toDateString() . ' 14:00:00',
            'end_time' => now()->addDay()->setHour(14)->toDateString() . ' 16:00:00',
            'vehicle_id' => $this->vehicle->id,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        // Trying to book same vehicle from 15:00 to 17:00 (overlaps!)
        $dto = new CreateScheduleDTO(
            branch_id: $this->branch->id,
            customer_id: $this->customer->id,
            title: 'Reparo Suspensão',
            start_time: now()->addDay()->setHour(15)->toDateString() . ' 15:00:00',
            end_time: now()->addDay()->setHour(15)->toDateString() . ' 17:00:00',
            vehicle_id: $this->vehicle->id,
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('O veículo selecionado já possui um agendamento conflitante neste horário.');

        $this->scheduleService->store($dto, $this->userId);
    });

    test('can cancel a schedule', function () {
        $schedule = Schedule::factory()->create([
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        $result = $this->scheduleService->cancel($schedule, $this->userId);

        expect($result)->toBeTrue();
        expect($schedule->fresh()->status)->toBe(Schedule::STATUS_CANCELLED);
    });

});

describe('Scheduling API Endpoints & Workflows', function () {

    beforeEach(function () {
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'scheduling-api-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);
    });

    test('can create a schedule via API', function () {
        $url = "http://scheduling-api-tenant.prime-erp.local/api/schedules";

        $response = $this->actingAs($this->user)->postJson($url, [
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Revisão Periódica 10k',
            'start_time' => now()->addDays(2)->setHour(8)->toDateString() . ' 08:00:00',
            'end_time' => now()->addDays(2)->setHour(8)->toDateString() . ' 10:00:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'title' => 'Revisão Periódica 10k',
            'status' => 'scheduled',
        ]);
    });

    test('can filter calendar schedules by start and end date via API', function () {
        // Schedule inside range
        $s1 = Schedule::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'start_time' => now()->addDays(2)->setHour(10)->toDateTimeString(),
            'end_time' => now()->addDays(2)->setHour(12)->toDateTimeString(),
        ]);

        // Schedule outside range
        $s2 = Schedule::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'start_time' => now()->addDays(10)->setHour(10)->toDateTimeString(),
            'end_time' => now()->addDays(10)->setHour(12)->toDateTimeString(),
        ]);

        $url = "http://scheduling-api-tenant.prime-erp.local/api/schedules";

        $response = $this->actingAs($this->user)->getJson($url . "?" . http_build_query([
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['id' => $s1->id]);
    });

    test('can cancel a schedule via API route', function () {
        $schedule = Schedule::factory()->create([
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        $url = "http://scheduling-api-tenant.prime-erp.local/api/schedules/{$schedule->id}/cancel";

        $response = $this->actingAs($this->user)->postJson($url);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'cancelled']);
    });

});
