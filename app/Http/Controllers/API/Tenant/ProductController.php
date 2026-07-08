<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCategory;
use App\Services\Tenant\ProductService;
use App\DTOs\Tenant\CreateProductDTO;
use App\DTOs\Tenant\UpdateProductDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    /**
     * GET /api/products - List all products
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query();

        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->has('search')) {
            $query->searchable($request->get('search'));
        }

        if ($request->get('status') === 'low_stock') {
            $query->lowStock();
        } elseif ($request->get('status') === 'out_of_stock') {
            $query->outOfStock();
        } else {
            $query->active();
        }

        $products = $query
            ->with('category')
            ->paginate(20);

        return response()->json($products);
    }

    /**
     * POST /api/products - Create a new product
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'category_id' => 'required|uuid|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products',
            'description' => 'nullable|string',
            'unit_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'min_stock_level' => 'nullable|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'unit_of_measure' => 'nullable|string|max:10',
        ]);

        try {
            $dto = CreateProductDTO::fromRequest($validated);
            $product = $this->service->create($dto);

            return response()->json($product->load('category'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/products/{id} - Get a specific product
     */
    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return response()->json($product->load('category'));
    }

    /**
     * PUT /api/products/{id} - Update a product
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'unit_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'min_stock_level' => 'nullable|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $dto = UpdateProductDTO::fromRequest($validated);
            $product = $this->service->update($product, $dto);

            return response()->json($product->load('category'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/products/{id} - Delete a product
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->service->delete($product);

        return response()->json(null, 204);
    }

    /**
     * POST /api/products/{id}/adjust-stock - Adjust product stock
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'quantity' => 'required|integer',
            'reason' => 'required|string',
        ]);

        $product = $this->service->adjustStock($product, $validated['quantity'], $validated['reason']);

        return response()->json($product->load('category'));
    }

    /**
     * GET /api/products/category/{categoryId} - Get products by category
     */
    public function byCategory(ProductCategory $category): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->service->getByCategory($category->id);

        return response()->json($products);
    }

    /**
     * GET /api/products/status/low-stock - Get low stock products
     */
    public function lowStock(): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->service->getLowStockProducts();

        return response()->json($products);
    }

    /**
     * GET /api/products/stats - Get product statistics
     */
    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $stats = $this->service->getProductStats();

        return response()->json($stats);
    }
}
