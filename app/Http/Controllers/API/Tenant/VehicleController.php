<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    /**
     * GET /api/vehicles - List all vehicles with pagination and search/filters
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $query = Vehicle::with('customer');

        if ($request->has('search') && !empty($request->get('search'))) {
            $term = $request->get('search');
            $query->where(function ($q) use ($term) {
                $q->where('plate', 'like', "%{$term}%")
                  ->orWhere('fleet_number', 'like', "%{$term}%")
                  ->orWhere('model', 'like', "%{$term}%")
                  ->orWhere('brand', 'like', "%{$term}%")
                  ->orWhere('vin', 'like', "%{$term}%")
                  ->orWhereHas('customer', function ($cq) use ($term) {
                      $cq->where('name', 'like', "%{$term}%")
                         ->orWhere('trade_name', 'like', "%{$term}%")
                         ->orWhere('cpf_cnpj', 'like', "%{$term}%");
                  });
            });
        }

        if ($request->has('customer_id') && !empty($request->get('customer_id'))) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        if ($request->has('status') && !empty($request->get('status'))) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('type') && !empty($request->get('type'))) {
            $query->where('type', $request->get('type'));
        }

        $vehicles = $query->latest()->paginate(20);

        return response()->json($vehicles);
    }

    /**
     * POST /api/vehicles - Create a new vehicle
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:tenant.customers,id',
            'plate' => 'required|string|unique:tenant.vehicles,plate',
            'fleet_number' => 'nullable|string|max:50',
            'model' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'type' => 'required|in:truck,van,car,motorcycle,trailer',
            'vin' => 'nullable|string|unique:tenant.vehicles,vin',
            'color' => 'nullable|string|max:100',
            'fuel_type' => 'nullable|string|max:30',
            'engine_type' => 'nullable|string|max:100',
            'axles' => 'nullable|string|max:50',
            'body_type' => 'nullable|string|max:80',
            'capacity_tons' => 'nullable|numeric|min:0',
            'renavam' => 'nullable|string|max:50',
            'license_expiration' => 'nullable|date',
            'status' => 'nullable|in:active,inactive,maintenance',
            'odometer' => 'nullable|integer|min:0',
            'branch_id' => 'nullable|exists:tenant.branches,id',
        ]);

        $vehicle = Vehicle::create($validated);
        $vehicle->load('customer');

        return response()->json($vehicle, 201);
    }

    /**
     * GET /api/vehicles/{id} - View vehicle details
     */
    public function show(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $vehicle->load('customer');

        return response()->json($vehicle);
    }

    /**
     * PUT /api/vehicles/{id} - Update vehicle
     */
    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:tenant.customers,id',
            'plate' => 'required|string|unique:tenant.vehicles,plate,' . $vehicle->id,
            'fleet_number' => 'nullable|string|max:50',
            'model' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'type' => 'required|in:truck,van,car,motorcycle,trailer',
            'vin' => 'nullable|string|unique:tenant.vehicles,vin,' . $vehicle->id,
            'color' => 'nullable|string|max:100',
            'fuel_type' => 'nullable|string|max:30',
            'engine_type' => 'nullable|string|max:100',
            'axles' => 'nullable|string|max:50',
            'body_type' => 'nullable|string|max:80',
            'capacity_tons' => 'nullable|numeric|min:0',
            'renavam' => 'nullable|string|max:50',
            'license_expiration' => 'nullable|date',
            'status' => 'nullable|in:active,inactive,maintenance',
            'odometer' => 'nullable|integer|min:0',
            'branch_id' => 'nullable|exists:tenant.branches,id',
        ]);

        $vehicle->update($validated);
        $vehicle->load('customer');

        return response()->json($vehicle);
    }

    /**
     * DELETE /api/vehicles/{id} - Delete vehicle
     */
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $vehicle->delete();

        return response()->json(['message' => 'Veículo excluído com sucesso.']);
    }
}
