<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderOfService;
use App\Services\Tenant\QualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualityMetricsController extends Controller
{
    public function __construct(
        protected QualityService $qualityService
    ) {}

    public function metrics(Request $request): JsonResponse
    {
        // Restrict metrics endpoint to staff
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        $metrics = $this->qualityService->getQualityMetrics();

        return response()->json($metrics);
    }

    public function registerFeedback(Request $request, OrderOfService $order): JsonResponse
    {
        // Enforce customer auth
        if (!$request->user() instanceof \App\Models\Tenant\Customer) {
            return response()->json(['error' => 'Acesso restrito a clientes.'], 403);
        }

        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'nps_score' => 'required|integer|min:0|max:10',
            'comments' => 'nullable|string',
        ]);

        try {
            $feedback = $this->qualityService->registerFeedback(
                $order,
                $validated['rating'],
                $validated['nps_score'],
                $validated['comments'] ?? null
            );

            return response()->json($feedback, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function issueWarranty(Request $request, OrderOfService $order): JsonResponse
    {
        // Enforce staff auth
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:full,parts,labor',
            'duration_days' => 'required|integer|min:1',
            'terms' => 'nullable|string',
        ]);

        try {
            $warranty = $this->qualityService->issueWarranty(
                $order,
                $validated['type'],
                $validated['duration_days'],
                $validated['terms'] ?? null
            );

            return response()->json($warranty, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
