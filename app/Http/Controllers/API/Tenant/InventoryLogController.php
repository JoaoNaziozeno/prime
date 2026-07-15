<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\InventoryLog;
use App\Models\Tenant\Product;
use App\Services\Tenant\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryLogController extends Controller
{
    public function __construct(private InventoryService $service) {}

    /**
     * GET /api/inventory-logs - List all inventory movements/logs
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InventoryLog::class);

        $query = InventoryLog::query();

        if ($request->has('product_id')) {
            $query->where('product_id', $request->get('product_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        $logs = $query->with('product')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($logs);
    }

    /**
     * GET /api/inventory-logs/{id} - Show a specific log
     */
    public function show(InventoryLog $inventoryLog): JsonResponse
    {
        $this->authorize('view', $inventoryLog);

        return response()->json($inventoryLog->load('product'));
    }

    /**
     * POST /api/inventory-logs - Record a manual movement or adjustment
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', InventoryLog::class);

        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:tenant.products,id',
            'type' => 'required|string|in:inbound,outbound,adjustment',
            'quantity' => 'required|integer|min:1',
            'is_adjustment_absolute' => 'nullable|boolean',
            'unit_cost' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'reference_type' => 'nullable|string|max:255',
            'reference_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $type = $validated['type'];
        $quantity = $validated['quantity'];

        try {
            if ($type === 'adjustment' && ($validated['is_adjustment_absolute'] ?? false)) {
                $log = $this->service->adjustStock($product, $quantity, $validated['notes'] ?? null);
            } else {
                $options = [
                    'unit_cost' => $validated['unit_cost'] ?? null,
                    'unit_price' => $validated['unit_price'] ?? null,
                    'reference_type' => $validated['reference_type'] ?? null,
                    'reference_id' => $validated['reference_id'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ];

                if ($type === 'adjustment') {
                    $options['relative_quantity'] = $quantity;
                }

                $log = $this->service->recordMovement($product, $type, $quantity, $options);
            }

            return response()->json($log->load('product'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
