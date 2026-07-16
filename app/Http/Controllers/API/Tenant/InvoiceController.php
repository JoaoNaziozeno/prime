<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Invoice;
use App\Services\Tenant\InvoiceService;
use App\DTOs\Tenant\CreateInvoiceDTO;
use App\DTOs\Tenant\UpdateInvoiceDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

    /**
     * GET /api/invoices - List invoices
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::query();

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        $invoices = $query->with(['customer', 'branch', 'items'])->paginate(20);

        return response()->json($invoices);
    }

    /**
     * POST /api/invoices - Create standalone invoice
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate([
            'order_of_service_id' => 'nullable|uuid|exists:orders_of_service,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'payment_term_id' => 'nullable|uuid|exists:payment_terms,id',
            'payment_method_id' => 'nullable|uuid|exists:payment_methods,id',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.itemable_type' => 'nullable|string',
            'items.*.itemable_id' => 'nullable|uuid',
        ]);

        try {
            $dto = CreateInvoiceDTO::fromRequest($validated);
            $invoice = $this->invoiceService->store($dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

            return response()->json($invoice->load(['customer', 'branch', 'items']), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/invoices/{id} - Get a specific invoice
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);
        return response()->json($invoice->load(['customer', 'branch', 'items', 'paymentTerm', 'paymentMethod']));
    }

    /**
     * PUT /api/invoices/{id} - Update an invoice
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'payment_term_id' => 'nullable|uuid|exists:payment_terms,id',
            'payment_method_id' => 'nullable|uuid|exists:payment_methods,id',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
            'status' => 'nullable|string|in:draft,sent,cancelled',
        ]);

        try {
            $dto = UpdateInvoiceDTO::fromRequest($validated);
            $invoice = $this->invoiceService->update($invoice, $dto, auth()->id() ?? '00000000-0000-0000-0000-000000000000');

            return response()->json($invoice->load(['customer', 'branch', 'items']));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * DELETE /api/invoices/{id} - Delete an invoice
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/orders/{order}/invoice - Create invoice from Order of Service
     */
    public function createFromOrder(Request $request, OrderOfService $order): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate([
            'payment_term_id' => 'nullable|uuid|exists:payment_terms,id',
            'payment_method_id' => 'nullable|uuid|exists:payment_methods,id',
        ]);

        try {
            $invoice = $this->invoiceService->createFromOrder(
                $order,
                $validated['payment_term_id'] ?? null,
                $validated['payment_method_id'] ?? null,
                auth()->id() ?? '00000000-0000-0000-0000-000000000000'
            );

            return response()->json($invoice->load(['customer', 'branch', 'items']), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/invoices/{id}/send - Send an invoice
     */
    public function send(Invoice $invoice): JsonResponse
    {
        $this->authorize('send', $invoice);

        try {
            $this->invoiceService->send($invoice, auth()->id() ?? '00000000-0000-0000-0000-000000000000');
            return response()->json($invoice->refresh());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/invoices/{id}/pay - Record a payment
     */
    public function pay(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('recordPayment', $invoice);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $this->invoiceService->recordPayment($invoice, $validated['amount'], auth()->id() ?? '00000000-0000-0000-0000-000000000000');
            return response()->json($invoice->refresh());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/invoices/{id}/cancel - Cancel an invoice
     */
    public function cancel(Invoice $invoice): JsonResponse
    {
        $this->authorize('cancel', $invoice);

        try {
            $this->invoiceService->cancel($invoice, auth()->id() ?? '00000000-0000-0000-0000-000000000000');
            return response()->json($invoice->refresh());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
