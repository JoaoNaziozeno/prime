<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $query = Supplier::query();

        if ($request->has('search')) {
            $term = $request->get('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('cnpj', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $suppliers = $query->paginate(20);

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:20|unique:tenant.suppliers,cnpj',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supplier = Supplier::create($validated);

        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cnpj' => 'nullable|string|max:20|unique:tenant.suppliers,cnpj,' . $supplier->id,
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supplier->update($validated);

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchaseOrders()->exists()) {
            return response()->json([
                'error' => 'Não é possível excluir um fornecedor com ordens de compra associadas.'
            ], 422);
        }

        $supplier->delete();

        return response()->json(null, 204);
    }
}
