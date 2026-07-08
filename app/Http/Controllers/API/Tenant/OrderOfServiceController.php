<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderOfService;
use App\Services\Tenant\OrderService;
use App\DTOs\Tenant\CreateOrderDTO;
use App\DTOs\Tenant\UpdateOrderDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderOfServiceController extends Controller
{
    public function __construct(private OrderService $service) {}

    /**
     * GET /api/orders - List all orders with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OrderOfService::class);

        $query = OrderOfService::query();

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->get('priority'));
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        $orders = $query
            ->with(['customer', 'vehicle', 'branch', 'items'])
            ->paginate(20);

        return response()->json($orders);
    }

    /**
     * POST /api/orders - Create a new order
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', OrderOfService::class);

        $validated = $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
            'branch_id' => 'required|uuid|exists:branches,id',
            'vehicle_id' => 'nullable|uuid|exists:vehicles,id',
            'priority' => 'nullable|in:low,medium,high,critical',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'expected_end_date' => 'nullable|date',
            'estimated_cost' => 'nullable|numeric|min:0',
        ]);

        try {
            $dto = CreateOrderDTO::fromRequest($validated);
            $order = $this->service->create($dto, auth()->id());

            return response()->json($order->load(['customer', 'vehicle', 'branch']), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/orders/{id} - Get a specific order
     */
    public function show(OrderOfService $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json($order->load(['customer', 'vehicle', 'branch', 'items']));
    }

    /**
     * PUT /api/orders/{id} - Update an order
     */
    public function update(Request $request, OrderOfService $order): JsonResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'expected_end_date' => 'nullable|date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,medium,high,critical',
        ]);

        try {
            $dto = UpdateOrderDTO::fromRequest($validated);
            $order = $this->service->update($order, $dto, auth()->id());

            return response()->json($order->load(['customer', 'vehicle', 'branch']));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/orders/{id} - Delete an order
     */
    public function destroy(OrderOfService $order): JsonResponse
    {
        $this->authorize('delete', $order);

        $order->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/orders/{id}/approve - Approve an order
     */
    public function approve(Request $request, OrderOfService $order): JsonResponse
    {
        $this->authorize('approve', $order);

        $validated = $request->validate([
            'approved_amount' => 'required|numeric|min:0',
        ]);

        try {
            $order = $this->service->approve($order, auth()->id(), $validated['approved_amount']);

            return response()->json($order->load(['customer', 'vehicle', 'branch']));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/orders/{id}/start - Start an order
     */
    public function start(OrderOfService $order): JsonResponse
    {
        $this->authorize('start', $order);

        try {
            $order = $this->service->start($order, auth()->id());

            return response()->json($order->load(['customer', 'vehicle', 'branch']));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/orders/{id}/complete - Complete an order
     */
    public function complete(Request $request, OrderOfService $order): JsonResponse
    {
        $this->authorize('complete', $order);

        $validated = $request->validate([
            'actual_cost' => 'required|numeric|min:0',
        ]);

        try {
            $order = $this->service->complete($order, auth()->id(), $validated['actual_cost']);

            return response()->json($order->load(['customer', 'vehicle', 'branch']));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/orders/{id}/cancel - Cancel an order
     */
    public function cancel(Request $request, OrderOfService $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $validated = $request->validate([
            'reason' => 'nullable|string',
        ]);

        try {
            $order = $this->service->cancel($order, auth()->id(), $validated['reason'] ?? null);

            return response()->json($order->load(['customer', 'vehicle', 'branch']));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/orders/{id}/hold - Put order on hold
     */
    public function hold(Request $request, OrderOfService $order): JsonResponse
    {
        $this->authorize('hold', $order);

        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        try {
            $order = $this->service->hold($order, auth()->id(), $validated['reason']);

            return response()->json($order->load(['customer', 'vehicle', 'branch']));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/orders/{id}/resume - Resume a held order
     */
    public function resume(OrderOfService $order): JsonResponse
    {
        $this->authorize('resume', $order);

        try {
            $order = $this->service->resume($order, auth()->id());

            return response()->json($order->load(['customer', 'vehicle', 'branch']));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/orders/stats/summary - Get summary statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OrderOfService::class);

        $branchId = $request->get('branch_id');
        $stats = $this->service->getSummaryStats($branchId);

        return response()->json($stats);
    }
}
