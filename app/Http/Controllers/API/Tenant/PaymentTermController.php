<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PaymentTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentTermController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', PaymentTerm::class);
        $terms = PaymentTerm::all();
        return response()->json($terms);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PaymentTerm::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'days_until_due' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $term = PaymentTerm::create($validated);
        return response()->json($term, 201);
    }

    public function show(PaymentTerm $paymentTerm): JsonResponse
    {
        $this->authorize('view', $paymentTerm);
        return response()->json($paymentTerm);
    }

    public function update(Request $request, PaymentTerm $paymentTerm): JsonResponse
    {
        $this->authorize('update', $paymentTerm);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'days_until_due' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $paymentTerm->update($validated);
        return response()->json($paymentTerm);
    }

    public function destroy(PaymentTerm $paymentTerm): JsonResponse
    {
        $this->authorize('delete', $paymentTerm);
        $paymentTerm->delete();
        return response()->json(null, 204);
    }
}
