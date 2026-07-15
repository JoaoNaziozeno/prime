<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\WarehouseLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseLocationController extends Controller
{
    /**
     * GET /api/warehouse-locations - List all locations
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WarehouseLocation::class);

        $query = WarehouseLocation::query();

        if ($request->has('search')) {
            $term = $request->get('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $locations = $query->paginate(20);

        return response()->json($locations);
    }

    /**
     * POST /api/warehouse-locations - Create a new location
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WarehouseLocation::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:tenant.warehouse_locations,code',
            'description' => 'nullable|string',
        ]);

        $location = WarehouseLocation::create($validated);

        return response()->json($location, 201);
    }

    /**
     * GET /api/warehouse-locations/{id} - Get a specific location
     */
    public function show(WarehouseLocation $warehouseLocation): JsonResponse
    {
        $this->authorize('view', $warehouseLocation);

        return response()->json($warehouseLocation);
    }

    /**
     * PUT /api/warehouse-locations/{id} - Update a location
     */
    public function update(Request $request, WarehouseLocation $warehouseLocation): JsonResponse
    {
        $this->authorize('update', $warehouseLocation);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:tenant.warehouse_locations,code,' . $warehouseLocation->id,
            'description' => 'nullable|string',
        ]);

        $warehouseLocation->update($validated);

        return response()->json($warehouseLocation);
    }

    /**
     * DELETE /api/warehouse-locations/{id} - Delete a location
     */
    public function destroy(WarehouseLocation $warehouseLocation): JsonResponse
    {
        $this->authorize('delete', $warehouseLocation);

        // Check if there are any products stored here
        if ($warehouseLocation->products()->exists()) {
            return response()->json([
                'error' => 'Não é possível excluir uma localização contendo produtos associados.'
            ], 422);
        }

        $warehouseLocation->delete();

        return response()->json(null, 204);
    }
}
