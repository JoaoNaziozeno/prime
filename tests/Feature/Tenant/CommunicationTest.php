<?php

use App\Models\Tenant\NotificationPreference;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Driver;
use App\Models\Master\User;
use App\Models\Master\Tenant;
use App\Models\Master\Company;
use App\Notifications\OrderCompletedNotification;
use App\Notifications\CnhExpiringNotification;
use App\Contracts\Tenant\SmsGatewayInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

describe('Notification Delivery & Channel Routing', function () {

    beforeEach(function () {
        config(['tenancy.database.central_connection' => 'sqlite']);
        $this->user = User::factory()->superAdmin()->create(['phone' => '+5511999999999']);
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'communication-logic-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);

        $this->order = OrderOfService::factory()->completed()->create([
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'branch_id' => $this->branch->id,
        ]);
    });

    test('routes only active channels from preferences (e.g. mail and database by default)', function () {
        Notification::fake();

        // Trigger notification
        $this->user->notify(new OrderCompletedNotification($this->order));

        Notification::assertSentTo(
            $this->user,
            OrderCompletedNotification::class,
            function ($notification, $channels) {
                // By default, preferences resolver returns ['mail', 'database']
                return in_array('mail', $channels) && in_array('database', $channels) && !in_array('sms', $channels);
            }
        );
    });

    test('routes to sms when user has sms preference activated', function () {
        Notification::fake();

        // Activate SMS only
        NotificationPreference::create([
            'user_id' => $this->user->id,
            'event_type' => 'order_completed',
            'channels' => [
                'mail' => false,
                'database' => false,
                'sms' => true,
            ]
        ]);

        $this->user->notify(new OrderCompletedNotification($this->order));

        Notification::assertSentTo(
            $this->user,
            OrderCompletedNotification::class,
            function ($notification, $channels) {
                // Should only contain our custom SmsChannel
                return !in_array('mail', $channels) && !in_array('database', $channels) && in_array('App\Channels\SmsChannel', $channels);
            }
        );
    });

    test('mock SMS gateway writes properly to logs upon dispatch', function () {
        // Arrange log listener
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn($msg) => str_contains($msg, 'SMS SEND MOCK') && str_contains($msg, $this->order->reference_number));

        // Set SMS preference
        NotificationPreference::create([
            'user_id' => $this->user->id,
            'event_type' => 'order_completed',
            'channels' => [
                'mail' => false,
                'database' => false,
                'sms' => true,
            ]
        ]);

        // Act (actual dispatch, no log fakes)
        $this->user->notify(new OrderCompletedNotification($this->order));
    });

});

describe('Notification API Endpoints', function () {

    beforeEach(function () {
        config(['tenancy.database.central_connection' => 'sqlite']);
        $this->user = User::factory()->superAdmin()->create();
        $this->company = Company::factory()->create();
        $this->tenant = Tenant::factory()->active()->create([
            'company_id' => $this->company->id,
            'slug' => 'communication-api-tenant',
        ]);
        tenancy()->initialize($this->tenant);

        $this->branch = Branch::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['branch_id' => $this->branch->id]);

        $this->order = OrderOfService::factory()->completed()->create([
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'branch_id' => $this->branch->id,
        ]);
    });

    test('can list in-app notifications via API', function () {
        // Dispatch one notification to populate DB
        $this->user->notify(new OrderCompletedNotification($this->order));

        $url = "http://communication-api-tenant.prime-erp.local/api/notifications";

        $response = $this->actingAs($this->user)->getJson($url);
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'type' => OrderCompletedNotification::class,
        ]);
    });

    test('can mark notification as read via API route', function () {
        $this->user->notify(new OrderCompletedNotification($this->order));
        $notification = $this->user->unreadNotifications->first();

        $url = "http://communication-api-tenant.prime-erp.local/api/notifications/{$notification->id}/read";

        $response = $this->actingAs($this->user)->postJson($url);

        $response->assertStatus(200);
        expect($notification->fresh()->read())->toBeTrue();
    });

    test('can fetch and update notification preferences via API', function () {
        $url = "http://communication-api-tenant.prime-erp.local/api/notifications/preferences";

        // 1. GET preferences defaults
        $responseGet = $this->actingAs($this->user)->getJson($url);
        $responseGet->assertStatus(200);
        $responseGet->assertJsonFragment([
            'order_completed' => [
                'mail' => true,
                'database' => true,
                'sms' => false,
            ]
        ]);

        // 2. POST update preferences
        $responsePost = $this->actingAs($this->user)->postJson($url, [
            'preferences' => [
                [
                    'event_type' => 'order_completed',
                    'channels' => [
                        'mail' => false,
                        'database' => true,
                        'sms' => true,
                    ]
                ]
            ]
        ]);

        $responsePost->assertStatus(200);

        // Assert preference is saved in DB
        $pref = NotificationPreference::where('user_id', $this->user->id)
            ->where('event_type', 'order_completed')
            ->first();

        expect($pref->channels['mail'])->toBeFalse();
        expect($pref->channels['sms'])->toBeTrue();
    });

});
