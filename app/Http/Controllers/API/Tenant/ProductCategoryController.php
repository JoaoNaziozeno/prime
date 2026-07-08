<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Service;
use App\Models\Tenant\ServiceCategory;
use App\Services\Tenant\ServiceService;
use App\DTOs\Tenant\CreateServiceDTO;
use App\DTOs\Tenant\UpdateServiceDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(private ServiceService $service) {}

    /**
     * GET /api/services - List all services
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $query = Service::query();

        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->has('search')) {
            $query->searchable($request->get('search'));
        }

        $query->active();

        $services = $query
            ->with('category')
            ->paginate(20);

        return response()->json($services);
    }

    /**
     * POST /api/services - Create a new service
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Service::class);

        $validated = $request->validate([
            'category_id' => 'required|uuid|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:services',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'estimated_hours' => 'nullable|numeric|min:0',
        ]);

        try {
            $dto = CreateServiceDTO::fromRequest($validated);
            $service = $this->service->create($dto);

            return response()->json($service->load('category'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/services/{id} - Get a specific service
     */
    public function show(Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return response()->json($service->load('category'));
    }

    /**
     * PUT /api/services/{id} - Update a service
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'nullable|numeric|min:0',
            'estimated_hours' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $dto = UpdateServiceDTO::fromRequest($validated);
            $service = $this->service->update($service, $dto);

            return response()->json($service->load('category'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/services/{id} - Delete a service
     */
    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $this->service->delete($service);

        return response()->json(null, 204);
    }

    /**
     * GET /api/services/category/{categoryId} - Get services by category
     */
    public function byCategory(ServiceCategory $category): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $services = $this->service->getByCategory($category->id);

        return response()->json($services);
    }

    /**
     * GET /api/services/stats - Get service statistics
     */
    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $stats = $this->service->getServiceStats();

        return response()->json($stats);
    }
}
