<?php

namespace App\Services\Tenant;

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Branch;
use App\DTOs\Tenant\CreateOrderDTO;
use App\DTOs\Tenant\UpdateOrderDTO;
use Illuminate\Support\Collection;

class OrderService
{
    /**
     * Create a new order of service
     */
    public function create(CreateOrderDTO $dto, string $userId): OrderOfService
    {
        // Validate relationships exist
        $customer = Customer::findOrFail($dto->customer_id);
        $branch = Branch::findOrFail($dto->branch_id);

        if ($dto->vehicle_id) {
            Vehicle::findOrFail($dto->vehicle_id);
        }

        $order = OrderOfService::create([
            'customer_id' => $dto->customer_id,
            'vehicle_id' => $dto->vehicle_id,
            'branch_id' => $dto->branch_id,
            'created_by' => $userId,
            'status' => OrderOfService::STATUS_DRAFT,
            'priority' => $dto->priority ?? OrderOfService::PRIORITY_MEDIUM,
            'description' => $dto->description,
            'internal_notes' => $dto->internal_notes,
            'expected_end_date' => $dto->expected_end_date,
            'estimated_cost' => $dto->estimated_cost,
            'metadata' => [
                'source' => 'api',
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        return $order;
    }

    /**
     * Update an existing order
     */
    public function update(OrderOfService $order, UpdateOrderDTO $dto, string $userId): OrderOfService
    {
        // Only allow updates to draft and approved orders
        if (!$order->isDraft() && !$order->isApproved()) {
            throw new \InvalidArgumentException('Cannot update order in ' . $order->status . ' status');
        }

        $order->update([
            'description' => $dto->description ?? $order->description,
            'internal_notes' => $dto->internal_notes ?? $order->internal_notes,
            'expected_end_date' => $dto->expected_end_date ?? $order->expected_end_date,
            'estimated_cost' => $dto->estimated_cost ?? $order->estimated_cost,
            'priority' => $dto->priority ?? $order->priority,
        ]);

        $order->setMetadataValue('updated_at', now()->toIso8601String());
        $order->setMetadataValue('updated_by', $userId);
        $order->save();

        return $order;
    }

    /**
     * Approve an order for execution
     */
    public function approve(OrderOfService $order, string $userId, float $approvedAmount): OrderOfService
    {
        if (!$order->canApprove()) {
            throw new \InvalidArgumentException('Order cannot be approved from ' . $order->status . ' status');
        }

        $order->approve($userId, $approvedAmount);

        return $order;
    }

    /**
     * Start execution of an order
     */
    public function start(OrderOfService $order, string $userId): OrderOfService
    {
        if (!$order->canStart()) {
            throw new \InvalidArgumentException('Order cannot start from ' . $order->status . ' status');
        }

        $order->start($userId);

        return $order;
    }

    /**
     * Complete an order
     */
    public function complete(OrderOfService $order, string $userId, float $actualCost): OrderOfService
    {
        if (!$order->canComplete()) {
            throw new \InvalidArgumentException('Order cannot complete from ' . $order->status . ' status');
        }

        // Verify all items are completed
        $pendingItems = $order->items()->pending()->count();
        if ($pendingItems > 0) {
            throw new \InvalidArgumentException('Cannot complete order with pending items');
        }

        $order->complete($userId, $actualCost);

        return $order;
    }

    /**
     * Cancel an order
     */
    public function cancel(OrderOfService $order, string $userId, ?string $reason = null): OrderOfService
    {
        if (!$order->canCancel()) {
            throw new \InvalidArgumentException('Order cannot be cancelled from ' . $order->status . ' status');
        }

        $order->cancel($userId);

        if ($reason) {
            $order->setMetadataValue('cancellation_reason', $reason);
            $order->save();
        }

        return $order;
    }

    /**
     * Put order on hold
     */
    public function hold(OrderOfService $order, string $userId, string $reason): OrderOfService
    {
        if (!$order->hold($userId)) {
            throw new \InvalidArgumentException('Order cannot be held');
        }

        $order->setMetadataValue('hold_reason', $reason);
        $order->save();

        return $order;
    }

    /**
     * Resume a held order
     */
    public function resume(OrderOfService $order, string $userId): OrderOfService
    {
        if (!$order->resume($userId)) {
            throw new \InvalidArgumentException('Order cannot be resumed');
        }

        return $order;
    }

    /**
     * Get orders by status
     */
    public function getByStatus(string $status, ?string $branchId = null): Collection
    {
        $query = OrderOfService::where('status', $status);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * Get overdue orders
     */
    public function getOverdueOrders(?string $branchId = null): Collection
    {
        $query = OrderOfService::active()->where('expected_end_date', '<', now());

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * Get orders by priority
     */
    public function getByPriority(string $priority, ?string $branchId = null): Collection
    {
        $query = OrderOfService::where('priority', $priority);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * Get summary statistics for orders
     */
    public function getSummaryStats(?string $branchId = null): array
    {
        $query = OrderOfService::query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return [
            'total' => $query->count(),
            'draft' => $query->clone()->draft()->count(),
            'pending_approval' => $query->clone()->pendingApproval()->count(),
            'approved' => $query->clone()->approved()->count(),
            'in_progress' => $query->clone()->inProgress()->count(),
            'completed' => $query->clone()->completed()->count(),
            'cancelled' => $query->clone()->cancelled()->count(),
            'on_hold' => $query->clone()->onHold()->count(),
            'overdue' => $this->getOverdueOrders($branchId)->count(),
            'total_value' => (clone $query)->sum('estimated_cost'),
        ];
    }
}
