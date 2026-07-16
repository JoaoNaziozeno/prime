<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderOfService;
use App\Services\Tenant\CustomerPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffOrderMessageController extends Controller
{
    public function __construct(
        protected CustomerPortalService $customerPortalService
    ) {}

    public function index(Request $request, OrderOfService $order): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        // Simple standard policy authorization on the OrderOfService
        $this->authorize('view', $order);

        return response()->json($order->messages()->oldest()->get());
    }

    public function store(Request $request, OrderOfService $order): JsonResponse
    {
        if (!$request->user() instanceof \App\Models\Master\User) {
            return response()->json(['error' => 'Acesso restrito a funcionários.'], 403);
        }

        $this->authorize('update', $order);

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $message = $this->customerPortalService->sendMessage(
            $order,
            'user',
            (string) $request->user()->id,
            $validated['message']
        );

        return response()->json($message, 201);
    }
}
