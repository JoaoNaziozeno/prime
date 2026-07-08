<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\OrderOfService;
use App\Services\Tenant\OrderItemService;
use App\DTOs\Tenant\CreateOrderItemDTO;
use App\DTOs\Tenant\UpdateOrderItemDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    public function __construct(private OrderItemService $service) {}

    /**
     * GET /api/orders/{orderId}/items - List items for an order
     */
    public function index(OrderOfService $order): JsonResponse
    {
        $this->authorize('viewAny', OrderItem::class);

        $items = $order->items()->orderBy('sequence')->get();

        return response()->json($items);
    }

    /**
     * POST /api/orders/{orderId}/items - Create a new item
     */
    public function store(Request $request, OrderOfService $order): JsonResponse
    {
        $this->authorize('create', OrderItem::class);

        $validated = $request->validate([
            'description' => 'required|string',
            'notes' => 'nullable|string',
            'sequence' => 'nullable|integer|min:1',
            'quantity' => 'nullable|numeric|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|uuid|exists:users,id',
        ]);

        try {
            $validated['order_of_service_id'] = $order->id;
            $dto = CreateOrderItemDTO::fromRequest($validated);
            $item = $this->service->create($dto, auth()->id());

            return response()->json($item, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/items/{id} - Get a specific item
     */
    public function show(OrderItem $item): JsonResponse
    {
        $this->authorize('view', $item);

        return response()->json($item);
    }

    /**
     * PUT /api/items/{id} - Update an item
     */
    public function update(Request $request, OrderItem $item): JsonResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'sequence' => 'nullable|integer|min:1',
            'quantity' => 'nullable|numeric|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|uuid|exists:users,id',
        ]);

        try {
            $dto = UpdateOrderItemDTO::fromRequest($validated);
            $item = $this->service->update($item, $dto, auth()->id());

            return response()->json($item);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/items/{id} - Delete an item
     */
    public function destroy(OrderItem $item): JsonResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/items/{id}/start - Start working on an item
     */
    public function start(OrderItem $item): JsonResponse
    {
        $this->authorize('start', $item);

        try {
            $item = $this->service->start($item, auth()->id());

            return response()->json($item);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/items/{id}/complete - Complete an item
     */
    public function complete(Request $request, OrderItem $item): JsonResponse
    {
        $this->authorize('complete', $item);

        $validated = $request->validate([
            'actual_cost' => 'required|numeric|min:0',
            'hours_spent' => 'required|numeric|min:0',
        ]);

        try {
            $item = $this->service->complete($item, auth()->id(), $validated['actual_cost'], $validated['hours_spent']);

            return response()->json($item);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/items/{id}/cancel - Cancel an item
     */
    public function cancel(Request $request, OrderItem $item): JsonResponse
    {
        $this->authorize('cancel', $item);

        $validated = $request->validate([
            'reason' => 'nullable|string',
        ]);

        try {
            $item = $this->service->cancel($item, auth()->id(), $validated['reason'] ?? null);

            return response()->json($item);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/items/{id}/block - Block an item
     */
    public function block(Request $request, OrderItem $item): JsonResponse
    {
        $this->authorize('block', $item);

        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        try {
            $item = $this->service->block($item, auth()->id(), $validated['reason']);

            return response()->json($item);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/items/{id}/unblock - Unblock an item
     */
    public function unblock(OrderItem $item): JsonResponse
    {
        $this->authorize('unblock', $item);

        try {
            $item = $this->service->unblock($item, auth()->id());

            return response()->json($item);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
