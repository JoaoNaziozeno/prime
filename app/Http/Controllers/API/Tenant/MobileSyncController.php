<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderOfService;
use App\Services\Tenant\MobileSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSyncController extends Controller
{
    public function __construct(
        protected MobileSyncService $mobileSyncService
    ) {}

    public function syncPull(Request $request): JsonResponse
    {
        $lastSyncAt = $request->get('last_sync_at');
        $modelsStr = $request->get('models');

        $models = $modelsStr
            ? explode(',', $modelsStr)
            : ['orders', 'vehicles', 'customers', 'products', 'services'];

        $payload = $this->mobileSyncService->pullChanges($lastSyncAt, $models);

        return response()->json($payload);
    }

    public function syncPush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'changes' => 'required|array',
            'changes.*.model' => 'required|string|in:orders,vehicles,customers,products,services',
            'changes.*.id' => 'required|string',
            'changes.*.action' => 'nullable|string|in:upsert,delete',
            'changes.*.data' => 'required|array',
            'changes.*.updated_at' => 'nullable|string',
        ]);

        $result = $this->mobileSyncService->pushChanges($validated['changes'], (string) $request->user()->id);

        return response()->json($result);
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = OrderOfService::select([
                'id',
                'customer_id',
                'vehicle_id',
                'status',
                'priority',
                'reference_number',
                'expected_end_date',
            ])
            ->with([
                'customer:id,name',
                'vehicle:id,plate'
            ])
            ->latest()
            ->paginate(30);

        return response()->json($orders);
    }
}
