<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', PaymentMethod::class);
        $methods = PaymentMethod::all();
        return response()->json($methods);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PaymentMethod::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:payment_methods,code|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $method = PaymentMethod::create($validated);
        return response()->json($method, 201);
    }

    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('view', $paymentMethod);
        return response()->json($paymentMethod);
    }

    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('update', $paymentMethod);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:100|unique:payment_methods,code,' . $paymentMethod->id,
            'is_active' => 'nullable|boolean',
        ]);

        $paymentMethod->update($validated);
        return response()->json($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('delete', $paymentMethod);
        $paymentMethod->delete();
        return response()->json(null, 204);
    }
}
