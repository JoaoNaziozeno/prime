<?php

namespace App\Services\Tenant;

use App\Models\Tenant\OrderItem;
use App\Models\Tenant\OrderOfService;
use App\DTOs\Tenant\CreateOrderItemDTO;
use App\DTOs\Tenant\UpdateOrderItemDTO;

class OrderItemService
{
    /**
     * Create a new order item
     */
    public function create(CreateOrderItemDTO $dto, string $userId): OrderItem
    {
        // Validate order exists
        $order = OrderOfService::findOrFail($dto->order_of_service_id);

        // Calculate estimated cost if not provided
        $estimatedCost = $dto->estimated_cost ?? ($dto->unit_price * $dto->quantity);

        $item = OrderItem::create([
            'order_of_service_id' => $dto->order_of_service_id,
            'assigned_to' => $dto->assigned_to,
            'created_by' => $userId,
            'description' => $dto->description,
            'notes' => $dto->notes,
            'sequence' => $dto->sequence,
            'quantity' => $dto->quantity,
            'unit_price' => $dto->unit_price,
            'estimated_cost' => $estimatedCost,
            'status' => OrderItem::STATUS_PENDING,
            'metadata' => [
                'source' => 'api',
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        return $item;
    }

    /**
     * Update an order item
     */
    public function update(OrderItem $item, UpdateOrderItemDTO $dto, string $userId): OrderItem
    {
        // Only allow updates to pending and blocked items
        if (!$item->isPending() && !$item->isBlocked()) {
            throw new \InvalidArgumentException('Cannot update item in ' . $item->status . ' status');
        }

        $data = [];

        if ($dto->description !== null) {
            $data['description'] = $dto->description;
        }

        if ($dto->notes !== null) {
            $data['notes'] = $dto->notes;
        }

        if ($dto->sequence !== null) {
            $data['sequence'] = $dto->sequence;
        }

        if ($dto->quantity !== null) {
            $data['quantity'] = $dto->quantity;
        }

        if ($dto->unit_price !== null) {
            $data['unit_price'] = $dto->unit_price;
        }

        if ($dto->estimated_cost !== null) {
            $data['estimated_cost'] = $dto->estimated_cost;
        }

        if ($dto->assigned_to !== null) {
            $data['assigned_to'] = $dto->assigned_to;
        }

        $item->update($data);

        $item->setMetadataValue('updated_at', now()->toIso8601String());
        $item->setMetadataValue('updated_by', $userId);
        $item->save();

        return $item;
    }

    /**
     * Start working on an item
     */
    public function start(OrderItem $item, string $userId): OrderItem
    {
        if (!$item->canStart()) {
            throw new \InvalidArgumentException('Item cannot start from ' . $item->status . ' status');
        }

        $item->start($userId);

        return $item;
    }

    /**
     * Complete an item
     */
    public function complete(OrderItem $item, string $userId, float $actualCost, float $hoursSpent): OrderItem
    {
        if (!$item->canComplete()) {
            throw new \InvalidArgumentException('Item cannot complete from ' . $item->status . ' status');
        }

        $item->complete($userId, $actualCost, $hoursSpent);

        return $item;
    }

    /**
     * Cancel an item
     */
    public function cancel(OrderItem $item, string $userId, ?string $reason = null): OrderItem
    {
        if (!$item->canCancel()) {
            throw new \InvalidArgumentException('Item cannot be cancelled from ' . $item->status . ' status');
        }

        $item->cancel($userId);

        if ($reason) {
            $item->setMetadataValue('cancellation_reason', $reason);
            $item->save();
        }

        return $item;
    }

    /**
     * Block an item
     */
    public function block(OrderItem $item, string $userId, string $reason): OrderItem
    {
        if (!$item->block($userId)) {
            throw new \InvalidArgumentException('Item cannot be blocked');
        }

        $item->setMetadataValue('block_reason', $reason);
        $item->save();

        return $item;
    }

    /**
     * Unblock an item
     */
    public function unblock(OrderItem $item, string $userId): OrderItem
    {
        if (!$item->unblock($userId)) {
            throw new \InvalidArgumentException('Item cannot be unblocked');
        }

        return $item;
    }

    /**
     * Get items for an order
     */
    public function getByOrder(OrderOfService $order, ?string $status = null)
    {
        $query = OrderItem::where('order_of_service_id', $order->id);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('sequence')->get();
    }

    /**
     * Get items assigned to a user
     */
    public function getAssignedTo(string $userId, ?string $status = null)
    {
        $query = OrderItem::where('assigned_to', $userId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }
}
