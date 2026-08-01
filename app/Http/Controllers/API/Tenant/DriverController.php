<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    /**
     * GET /api/drivers - List all drivers/employees
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Driver::class);

        $query = Driver::query();

        if ($request->has('search') && !empty($request->get('search'))) {
            $term = $request->get('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%")
                  ->orWhere('cpf', 'like', "%{$term}%")
                  ->orWhere('cnh', 'like', "%{$term}%");
            });
        }

        if ($request->has('status') && !empty($request->get('status'))) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('cnh_category') && !empty($request->get('cnh_category'))) {
            $query->where('cnh_category', $request->get('cnh_category'));
        }

        $drivers = $query->paginate(20);

        return response()->json($drivers);
    }

    /**
     * POST /api/drivers - Create a new driver/employee
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Driver::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:tenant.drivers,email',
            'phone' => 'nullable|string|max:50',
            'cpf' => 'nullable|string|max:20|unique:tenant.drivers,cpf',
            'cnh' => 'nullable|string|max:20|unique:tenant.drivers,cnh',
            'cnh_category' => 'nullable|string|max:5',
            'cnh_expiration' => 'nullable|date',
            'status' => 'nullable|in:active,inactive,suspended',
            'hired_at' => 'nullable|date',
        ]);

        $driver = Driver::create($validated);

        return response()->json($driver, 201);
    }

    /**
     * GET /api/drivers/{id} - View specific driver
     */
    public function show(Driver $driver): JsonResponse
    {
        $this->authorize('view', $driver);

        return response()->json($driver);
    }

    /**
     * PUT /api/drivers/{id} - Update driver details
     */
    public function update(Request $request, Driver $driver): JsonResponse
    {
        $this->authorize('update', $driver);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:tenant.drivers,email,' . $driver->id,
            'phone' => 'nullable|string|max:50',
            'cpf' => 'nullable|string|max:20|unique:tenant.drivers,cpf,' . $driver->id,
            'cnh' => 'nullable|string|max:20|unique:tenant.drivers,cnh,' . $driver->id,
            'cnh_category' => 'nullable|string|max:5',
            'cnh_expiration' => 'nullable|date',
            'status' => 'nullable|in:active,inactive,suspended',
            'hired_at' => 'nullable|date',
        ]);

        $driver->update($validated);

        return response()->json($driver);
    }

    /**
     * DELETE /api/drivers/{id} - Delete driver
     */
    public function destroy(Driver $driver): JsonResponse
    {
        $this->authorize('delete', $driver);

        $driver->delete();

        return response()->json(['message' => 'Colaborador excluído com sucesso.']);
    }
}
