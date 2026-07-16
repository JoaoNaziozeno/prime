<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderOfService;
use App\Services\Tenant\CustomerPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPortalController extends Controller
{
    public function __construct(
        protected CustomerPortalService $customerPortalService
    ) {}

    public function profile(Request $request): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Tenant\Customer) {
            return response()->json(['error' => 'Acesso restrito a clientes.'], 403);
        }

        return response()->json($request->user());
    }

    public function orders(Request $request): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Tenant\Customer) {
            return response()->json(['error' => 'Acesso restrito a clientes.'], 403);
        }

        $orders = OrderOfService::where('customer_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function showOrder(Request $request, OrderOfService $order): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Tenant\Customer) {
            return response()->json(['error' => 'Acesso restrito a clientes.'], 403);
        }

        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        return response()->json($order->load(['productLines.product', 'serviceLines.service', 'items']));
    }

    public function messages(Request $request, OrderOfService $order): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Tenant\Customer) {
            return response()->json(['error' => 'Acesso restrito a clientes.'], 403);
        }

        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        return response()->json($order->messages()->oldest()->get());
    }

    public function sendMessage(Request $request, OrderOfService $order): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Tenant\Customer) {
            return response()->json(['error' => 'Acesso restrito a clientes.'], 403);
        }

        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $message = $this->customerPortalService->sendMessage(
            $order,
            'customer',
            (string) $request->user()->id,
            $validated['message']
        );

        return response()->json($message, 201);
    }
}
