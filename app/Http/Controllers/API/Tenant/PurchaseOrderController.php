<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PurchaseOrder;
use App\Services\Tenant\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $purchaseOrderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = PurchaseOrder::with(['supplier', 'branch']);

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->get('supplier_id'));
        }

        $pos = $query->paginate(20);

        return response()->json($pos);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $validated = $request->validate([
            'supplier_id' => 'required|exists:tenant.suppliers,id',
            'branch_id' => 'required|exists:tenant.branches,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:tenant.products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $po = $this->purchaseOrderService->create($validated, (string) $request->user()->id);

        return response()->json($po, 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        return response()->json($purchaseOrder->load('items.product'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:tenant.suppliers,id',
            'branch_id' => 'nullable|exists:tenant.branches,id',
            'notes' => 'nullable|string',
            'items' => 'nullable|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:tenant.products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_cost' => 'required_with:items|numeric|min:0',
        ]);

        $po = $this->purchaseOrderService->update($purchaseOrder, $validated, (string) $request->user()->id);

        return response()->json($po);
    }

    public function approve(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        $po = $this->purchaseOrderService->approve($purchaseOrder, (string) $request->user()->id);

        return response()->json([
            'message' => 'Ordem de compra aprovada com sucesso.',
            'purchase_order' => $po,
        ]);
    }

    public function cancel(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        $po = $this->purchaseOrderService->cancel($purchaseOrder, (string) $request->user()->id);

        return response()->json([
            'message' => 'Ordem de compra cancelada com sucesso.',
            'purchase_order' => $po,
        ]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('update', $purchaseOrder);

        $validated = $request->validate([
            'received_items' => 'required|array|min:1',
            'received_items.*.purchase_order_item_id' => 'required|exists:tenant.purchase_order_items,id',
            'received_items.*.quantity_received' => 'required|numeric|min:0',
            'received_items.*.serials' => 'nullable|array',
            'received_items.*.serials.*' => 'string',
            'received_items.*.batch_number' => 'nullable|string',
            'received_items.*.expiration_date' => 'nullable|date',
        ]);

        $po = $this->purchaseOrderService->receive($purchaseOrder, $validated, (string) $request->user()->id);

        return response()->json([
            'message' => 'Recebimento registrado com sucesso.',
            'purchase_order' => $po,
        ]);
    }
}
