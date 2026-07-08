<?php

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('OrderOfService Model', function () {

    test('create order of service with factory', function () {
        $order = OrderOfService::factory()->create();

        expect($order)->toBeInstanceOf(OrderOfService::class);
        expect($order->reference_number)->not->toBeEmpty();
        expect($order->status)->toBe(OrderOfService::STATUS_DRAFT);
    });

    test('order can be in all statuses', function () {
        $draft = OrderOfService::factory()->draft()->create();
        $pending = OrderOfService::factory()->pendingApproval()->create();
        $approved = OrderOfService::factory()->approved()->create();
        $inProgress = OrderOfService::factory()->inProgress()->create();
        $completed = OrderOfService::factory()->completed()->create();
        $cancelled = OrderOfService::factory()->cancelled()->create();
        $onHold = OrderOfService::factory()->onHold()->create();

        expect($draft->status)->toBe(OrderOfService::STATUS_DRAFT);
        expect($pending->status)->toBe(OrderOfService::STATUS_PENDING_APPROVAL);
        expect($approved->status)->toBe(OrderOfService::STATUS_APPROVED);
        expect($inProgress->status)->toBe(OrderOfService::STATUS_IN_PROGRESS);
        expect($completed->status)->toBe(OrderOfService::STATUS_COMPLETED);
        expect($cancelled->status)->toBe(OrderOfService::STATUS_CANCELLED);
        expect($onHold->status)->toBe(OrderOfService::STATUS_ON_HOLD);
    });

    test('order can have different priorities', function () {
        $low = OrderOfService::factory()->state(['priority' => OrderOfService::PRIORITY_LOW])->create();
        $medium = OrderOfService::factory()->state(['priority' => OrderOfService::PRIORITY_MEDIUM])->create();
        $high = OrderOfService::factory()->highPriority()->create();
        $critical = OrderOfService::factory()->criticalPriority()->create();

        expect($low->priority)->toBe(OrderOfService::PRIORITY_LOW);
        expect($medium->priority)->toBe(OrderOfService::PRIORITY_MEDIUM);
        expect($high->priority)->toBe(OrderOfService::PRIORITY_HIGH);
        expect($critical->priority)->toBe(OrderOfService::PRIORITY_CRITICAL);
    });

    test('order belongs to customer', function () {
        $customer = Customer::factory()->create();
        $order = OrderOfService::factory()->forCustomer($customer)->create();

        expect($order->customer_id)->toBe($customer->id);
        expect($order->customer)->toBeInstanceOf(Customer::class);
    });

    test('order belongs to vehicle', function () {
        $vehicle = Vehicle::factory()->create();
        $order = OrderOfService::factory()->forVehicle($vehicle)->create();

        expect($order->vehicle_id)->toBe($vehicle->id);
        expect($order->vehicle)->toBeInstanceOf(Vehicle::class);
    });

    test('order belongs to branch', function () {
        $branch = Branch::factory()->create();
        $order = OrderOfService::factory()->forBranch($branch)->create();

        expect($order->branch_id)->toBe($branch->id);
        expect($order->branch)->toBeInstanceOf(Branch::class);
    });

    test('order can have many items', function () {
        $order = OrderOfService::factory()->create();
        OrderItem::factory(5)->forOrder($order)->create();

        expect($order->items()->count())->toBe(5);
    });

    test('scopes filter orders correctly', function () {
        OrderOfService::factory(3)->draft()->create();
        OrderOfService::factory(2)->approved()->create();
        OrderOfService::factory(1)->completed()->create();

        expect(OrderOfService::draft()->count())->toBe(3);
        expect(OrderOfService::approved()->count())->toBe(2);
        expect(OrderOfService::completed()->count())->toBe(1);
    });

    test('active scope returns only active orders', function () {
        OrderOfService::factory(2)->draft()->create();
        OrderOfService::factory(2)->approved()->create();
        OrderOfService::factory(2)->inProgress()->create();
        OrderOfService::factory(2)->completed()->create();

        expect(OrderOfService::active()->count())->toBe(6);
    });

    test('order can transition to approved', function () {
        $order = OrderOfService::factory()->draft()->create();

        $result = $order->approve($order->created_by, 1000);

        expect($result)->toBeTrue();
        expect($order->refresh()->status)->toBe(OrderOfService::STATUS_APPROVED);
        expect($order->approved_amount)->toBe(1000);
    });

    test('order can transition to in progress', function () {
        $order = OrderOfService::factory()->approved()->create();

        $result = $order->start($order->created_by);

        expect($result)->toBeTrue();
        expect($order->refresh()->status)->toBe(OrderOfService::STATUS_IN_PROGRESS);
        expect($order->start_date)->not->toBeNull();
    });

    test('order can be completed', function () {
        $order = OrderOfService::factory()->inProgress()->create();

        $result = $order->complete($order->created_by, 1500);

        expect($result)->toBeTrue();
        expect($order->refresh()->status)->toBe(OrderOfService::STATUS_COMPLETED);
        expect($order->actual_end_date)->not->toBeNull();
        expect($order->actual_cost)->toBe(1500);
    });

    test('order can be cancelled', function () {
        $order = OrderOfService::factory()->approved()->create();

        $result = $order->cancel($order->created_by);

        expect($result)->toBeTrue();
        expect($order->refresh()->status)->toBe(OrderOfService::STATUS_CANCELLED);
    });

    test('order can be put on hold', function () {
        $order = OrderOfService::factory()->inProgress()->create();

        $result = $order->hold($order->created_by);

        expect($result)->toBeTrue();
        expect($order->refresh()->status)->toBe(OrderOfService::STATUS_ON_HOLD);
    });

    test('order can be resumed from hold', function () {
        $order = OrderOfService::factory()->onHold()->create();

        $result = $order->resume($order->created_by);

        expect($result)->toBeTrue();
        expect($order->refresh()->status)->toBe(OrderOfService::STATUS_IN_PROGRESS);
    });

    test('order status checks work correctly', function () {
        $draft = OrderOfService::factory()->draft()->create();
        $approved = OrderOfService::factory()->approved()->create();
        $inProgress = OrderOfService::factory()->inProgress()->create();
        $completed = OrderOfService::factory()->completed()->create();

        expect($draft->isDraft())->toBeTrue();
        expect($approved->isApproved())->toBeTrue();
        expect($inProgress->isInProgress())->toBeTrue();
        expect($completed->isCompleted())->toBeTrue();
    });

    test('order calculates cost variance', function () {
        $order = OrderOfService::factory()->completed()->create([
            'estimated_cost' => 1000,
            'actual_cost' => 1200,
        ]);

        expect($order->getCostVariance())->toBe(200);
        expect($order->getCostVariancePercentage())->toBeCloseTo(20, 0.1);
    });

    test('order progress percentage calculated correctly', function () {
        $order = OrderOfService::factory()->inProgress()->create();
        OrderItem::factory(4)->pending()->forOrder($order)->create();
        OrderItem::factory(1)->completed()->forOrder($order)->create();

        expect($order->getProgressPercentage())->toBe(20);
    });

    test('order expiration detected correctly', function () {
        $expired = OrderOfService::factory()->create([
            'expected_end_date' => now()->subDay(),
        ]);
        $notExpired = OrderOfService::factory()->create([
            'expected_end_date' => now()->addDay(),
        ]);

        expect($expired->isExpired())->toBeTrue();
        expect($notExpired->isExpired())->toBeFalse();
    });

    test('order metadata can be stored', function () {
        $order = OrderOfService::factory()->create();

        $order->setMetadataValue('custom_field', 'custom_value');

        expect($order->getMetadataValue('custom_field'))->toBe('custom_value');
    });

});

describe('OrderItem Model', function () {

    test('create order item with factory', function () {
        $item = OrderItem::factory()->create();

        expect($item)->toBeInstanceOf(OrderItem::class);
        expect($item->description)->not->toBeEmpty();
        expect($item->status)->toBe(OrderItem::STATUS_PENDING);
    });

    test('item can be in all statuses', function () {
        $pending = OrderItem::factory()->pending()->create();
        $inProgress = OrderItem::factory()->inProgress()->create();
        $completed = OrderItem::factory()->completed()->create();
        $cancelled = OrderItem::factory()->cancelled()->create();
        $blocked = OrderItem::factory()->blocked()->create();

        expect($pending->status)->toBe(OrderItem::STATUS_PENDING);
        expect($inProgress->status)->toBe(OrderItem::STATUS_IN_PROGRESS);
        expect($completed->status)->toBe(OrderItem::STATUS_COMPLETED);
        expect($cancelled->status)->toBe(OrderItem::STATUS_CANCELLED);
        expect($blocked->status)->toBe(OrderItem::STATUS_BLOCKED);
    });

    test('item belongs to order', function () {
        $order = OrderOfService::factory()->create();
        $item = OrderItem::factory()->forOrder($order)->create();

        expect($item->order_of_service_id)->toBe($order->id);
        expect($item->order)->toBeInstanceOf(OrderOfService::class);
    });

    test('item can be assigned to user', function () {
        $userId = fake()->uuid();
        $item = OrderItem::factory()->assigned($userId)->create();

        expect($item->assigned_to)->toBe($userId);
    });

    test('item scopes filter correctly', function () {
        OrderItem::factory(3)->pending()->create();
        OrderItem::factory(2)->inProgress()->create();
        OrderItem::factory(1)->completed()->create();

        expect(OrderItem::pending()->count())->toBe(3);
        expect(OrderItem::inProgress()->count())->toBe(2);
        expect(OrderItem::completed()->count())->toBe(1);
    });

    test('item can transition to in progress', function () {
        $item = OrderItem::factory()->pending()->create();

        $result = $item->start($item->created_by);

        expect($result)->toBeTrue();
        expect($item->refresh()->status)->toBe(OrderItem::STATUS_IN_PROGRESS);
        expect($item->started_at)->not->toBeNull();
    });

    test('item can be completed', function () {
        $item = OrderItem::factory()->inProgress()->create();

        $result = $item->complete($item->created_by, 500, 2.5);

        expect($result)->toBeTrue();
        expect($item->refresh()->status)->toBe(OrderItem::STATUS_COMPLETED);
        expect($item->completed_at)->not->toBeNull();
        expect($item->actual_cost)->toBe(500);
        expect($item->hours_spent)->toBe(2.5);
    });

    test('item can be cancelled', function () {
        $item = OrderItem::factory()->pending()->create();

        $result = $item->cancel($item->created_by);

        expect($result)->toBeTrue();
        expect($item->refresh()->status)->toBe(OrderItem::STATUS_CANCELLED);
    });

    test('item can be blocked', function () {
        $item = OrderItem::factory()->pending()->create();

        $result = $item->block($item->created_by);

        expect($result)->toBeTrue();
        expect($item->refresh()->status)->toBe(OrderItem::STATUS_BLOCKED);
    });

    test('item can be unblocked', function () {
        $item = OrderItem::factory()->blocked()->create();

        $result = $item->unblock($item->created_by);

        expect($result)->toBeTrue();
        expect($item->refresh()->status)->toBe(OrderItem::STATUS_PENDING);
    });

    test('item status checks work correctly', function () {
        $pending = OrderItem::factory()->pending()->create();
        $inProgress = OrderItem::factory()->inProgress()->create();
        $completed = OrderItem::factory()->completed()->create();

        expect($pending->isPending())->toBeTrue();
        expect($inProgress->isInProgress())->toBeTrue();
        expect($completed->isCompleted())->toBeTrue();
    });

    test('item cost variance calculated correctly', function () {
        $item = OrderItem::factory()->completed()->create([
            'estimated_cost' => 1000,
            'actual_cost' => 1100,
        ]);

        expect($item->getCostVariance())->toBe(100);
        expect($item->getCostVariancePercentage())->toBeCloseTo(10, 0.1);
    });

    test('item metadata can be stored', function () {
        $item = OrderItem::factory()->create();

        $item->setMetadataValue('note', 'test note');

        expect($item->getMetadataValue('note'))->toBe('test note');
    });

});
