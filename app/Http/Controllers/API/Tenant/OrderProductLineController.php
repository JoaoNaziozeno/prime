<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderProductLine;
use App\Services\Tenant\CostAllocationService;
use App\DTOs\Tenant\CreateOrderProductLineDTO;
use App\DTOs\Tenant\UpdateOrderProductLineDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderProductLineController extends Controller
{
    public function __construct(
        private CostAllocationService $costAllocationService
    ) {}

    /**
     * List all product lines for an order
     */
    public function index(OrderOfService $order): JsonResponse
    {
        $this->authorize('viewAny', OrderProductLine::class);

        $lines = $order->productLines()->with('product')->get();

        return response()->json($lines);
    }

    /**
     * Add a product line to an order
     */
    public function store(Request $request, OrderOfService $order): JsonResponse
    {
        $this->authorize('create', OrderProductLine::class);

        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        try {
            $dto = CreateOrderProductLineDTO::fromRequest($validated);
            $line = $this->costAllocationService->addProductLine($order, $dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

            return response()->json($line->load('product'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get a specific product line
     */
    public function show(OrderOfService $order, OrderProductLine $product): JsonResponse
    {
        $this->authorize('view', $product);

        return response()->json($product->load('product'));
    }

    /**
     * Update a product line
     */
    public function update(Request $request, OrderOfService $order, OrderProductLine $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'quantity' => 'nullable|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        try {
            $dto = UpdateOrderProductLineDTO::fromRequest($validated);
            $line = $this->costAllocationService->updateProductLine($product, $dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

            return response()->json($line->load('product'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Delete a product line
     */
    public function destroy(OrderOfService $order, OrderProductLine $product): JsonResponse
    {
        $this->authorize('delete', $product);

        try {
            $this->costAllocationService->removeProductLine($product, auth()->id() ?? '00000000-0000-0000-0000-000000000000');
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
