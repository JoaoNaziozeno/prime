<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * GET /api/customers - Listar todos os clientes com paginação e busca
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()->withCount('vehicles');

        if ($request->has('search') && !empty($request->get('search'))) {
            $term = $request->get('search');
            $cleanTerm = ltrim(trim($term), '#');
            $query->where(function ($q) use ($term, $cleanTerm) {
                if (is_numeric($cleanTerm)) {
                    $q->orWhere('id', $cleanTerm);
                }
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('trade_name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%")
                  ->orWhere('contact_name', 'like', "%{$term}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$term}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('type') && !empty($request->get('type'))) {
            $query->where('type', $request->get('type'));
        }

        $customers = $query->paginate(20);

        return response()->json($customers);
    }

    /**
     * POST /api/customers - Criar um novo cliente
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:tenant.customers,email',
            'phone' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:255',
            'cpf_cnpj' => 'nullable|string|max:20|unique:tenant.customers,cpf_cnpj',
            'state_registration' => 'nullable|string|max:30',
            'type' => 'required|in:individual,company',
            'street' => 'nullable|string|max:255',
            'number' => 'nullable|string|max:50',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive,suspended',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::create($validated);

        return response()->json($customer, 201);
    }

    /**
     * GET /api/customers/{id} - Exibir detalhes de um cliente
     */
    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $customer->loadCount('vehicles')->load('vehicles');

        return response()->json($customer);
    }

    /**
     * PUT /api/customers/{id} - Atualizar dados do cliente
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:tenant.customers,email,' . $customer->id,
            'phone' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:255',
            'cpf_cnpj' => 'nullable|string|max:20|unique:tenant.customers,cpf_cnpj,' . $customer->id,
            'state_registration' => 'nullable|string|max:30',
            'type' => 'required|in:individual,company',
            'street' => 'nullable|string|max:255',
            'number' => 'nullable|string|max:50',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive,suspended',
            'notes' => 'nullable|string',
        ]);

        $customer->update($validated);

        return response()->json($customer);
    }

    /**
     * DELETE /api/customers/{id} - Exclusão lógica (Soft Delete) do cliente
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return response()->json(['message' => 'Cliente excluído com sucesso.']);
    }

    /**
     * GET /api/customers/stats/summary - Métricas consolidadas de clientes
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        return response()->json([
            'total' => Customer::count(),
            'active' => Customer::where('status', 'active')->count(),
            'inactive' => Customer::where('status', 'inactive')->count(),
            'suspended' => Customer::where('status', 'suspended')->count(),
            'company' => Customer::where('type', 'company')->count(),
            'individual' => Customer::where('type', 'individual')->count(),
        ]);
    }
}
