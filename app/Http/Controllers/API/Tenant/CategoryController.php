<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * GET /api/product-categories - List product categories
     */
    public function indexProductCategories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductCategory::class);

        $query = ProductCategory::query();

        if ($request->get('active_only', false)) {
            $query->active();
        }

        $categories = $query->withCount('products')->paginate(50);

        return response()->json($categories);
    }

    /**
     * POST /api/product-categories - Create product category
     */
    public function storeProductCategory(Request $request): JsonResponse
    {
        $this->authorize('create', ProductCategory::class);

        $validated = $request->validate([
            'name' => 'required|string|unique:product_categories',
            'description' => 'nullable|string',
        ]);

        $category = ProductCategory::create($validated);

        return response()->json($category, 201);
    }

    /**
     * GET /api/service-categories - List service categories
     */
    public function indexServiceCategories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCategory::class);

        $query = ServiceCategory::query();

        if ($request->get('active_only', false)) {
            $query->active();
        }

        $categories = $query->withCount('services')->paginate(50);

        return response()->json($categories);
    }

    /**
     * POST /api/service-categories - Create service category
     */
    public function storeServiceCategory(Request $request): JsonResponse
    {
        $this->authorize('create', ServiceCategory::class);

        $validated = $request->validate([
            'name' => 'required|string|unique:service_categories',
            'description' => 'nullable|string',
        ]);

        $category = ServiceCategory::create($validated);

        return response()->json($category, 201);
    }

    /**
     * PUT /api/product-categories/{id} - Update product category
     */
    public function updateProductCategory(Request $request, ProductCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => 'nullable|string|unique:product_categories,name,' . $category->id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * PUT /api/service-categories/{id} - Update service category
     */
    public function updateServiceCategory(Request $request, ServiceCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => 'nullable|string|unique:service_categories,name,' . $category->id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * DELETE /api/product-categories/{id} - Delete product category
     */
    public function destroyProductCategory(ProductCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(null, 204);
    }

    /**
     * DELETE /api/service-categories/{id} - Delete service category
     */
    public function destroyServiceCategory(ServiceCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(null, 204);
    }
}
