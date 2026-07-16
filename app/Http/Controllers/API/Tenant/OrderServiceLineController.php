<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderServiceLine;
use App\Services\Tenant\CostAllocationService;
use App\DTOs\Tenant\CreateOrderServiceLineDTO;
use App\DTOs\Tenant\UpdateOrderServiceLineDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderServiceLineController extends Controller
{
    public function __construct(
        private CostAllocationService $costAllocationService
    ) {}

    /**
     * List all service lines for an order
     */
    public function index(OrderOfService $order): JsonResponse
    {
        $this->authorize('viewAny', OrderServiceLine::class);

        $lines = $order->serviceLines()->with('service')->get();

        return response()->json($lines);
    }

    /**
     * Add a service line to an order
     */
    public function store(Request $request, OrderOfService $order): JsonResponse
    {
        $this->authorize('create', OrderServiceLine::class);

        $validated = $request->validate([
            'service_id' => 'required|uuid|exists:services,id',
            'assigned_to' => 'nullable|uuid',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        try {
            $dto = CreateOrderServiceLineDTO::fromRequest($validated);
            $line = $this->costAllocationService->addServiceLine($order, $dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

            return response()->json($line->load('service'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get a specific service line
     */
    public function show(OrderOfService $order, OrderServiceLine $service): JsonResponse
    {
        $this->authorize('view', $service);

        return response()->json($service->load('service'));
    }

    /**
     * Update a service line
     */
    public function update(Request $request, OrderOfService $order, OrderServiceLine $service): JsonResponse
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'assigned_to' => 'nullable|uuid',
            'quantity' => 'nullable|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
        ]);

        try {
            $dto = UpdateOrderServiceLineDTO::fromRequest($validated);
            $line = $this->costAllocationService->updateServiceLine($service, $dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

            return response()->json($line->load('service'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Delete a service line
     */
    public function destroy(OrderOfService $order, OrderServiceLine $service): JsonResponse
    {
        $this->authorize('delete', $service);

        try {
            $this->costAllocationService->removeServiceLine($service, auth()->id() ?? '00000000-0000-0000-0000-000000000000');
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Start execution of a service line
     */
    public function start(OrderOfService $order, OrderServiceLine $service): JsonResponse
    {
        $this->authorize('start', $service);

        try {
            $dto = new UpdateOrderServiceLineDTO(status: OrderServiceLine::STATUS_IN_PROGRESS);
            $line = $this->costAllocationService->updateServiceLine($service, $dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

            return response()->json($line->load('service'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Complete execution of a service line
     */
    public function complete(Request $request, OrderOfService $order, OrderServiceLine $service): JsonResponse
    {
        $this->authorize('complete', $service);

        $validated = $request->validate([
            'hours_spent' => 'nullable|numeric|min:0',
        ]);

        try {
            $dto = new UpdateOrderServiceLineDTO(
                quantity: isset($validated['hours_spent']) ? (float) $validated['hours_spent'] : null,
                status: OrderServiceLine::STATUS_COMPLETED
            );
            $line = $this->costAllocationService->updateServiceLine($service, $dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

            return response()->json($line->load('service'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel execution of a service line
     */
    public function cancel(OrderOfService $order, OrderServiceLine $service): JsonResponse
    {
        $this->authorize('cancel', $service);

        try {
            $dto = new UpdateOrderServiceLineDTO(status: OrderServiceLine::STATUS_CANCELLED);
            $line = $this->costAllocationService->updateServiceLine($service, $dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

            return response()->json($line->load('service'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
