<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Payment;
use App\Services\Tenant\PaymentProcessingService;
use App\DTOs\Tenant\ProcessPaymentDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentProcessingService $paymentProcessingService
    ) {}

    /**
     * GET /api/invoices/{invoice}/payments - List payments for an invoice
     */
    public function index(Invoice $invoice): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);
        $payments = $invoice->payments()->with('paymentMethod')->get();
        return response()->json($payments);
    }

    /**
     * POST /api/invoices/{invoice}/payments - Initiate a payment
     */
    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|uuid|exists:payment_methods,id',
            'gateway' => 'required|string|in:stripe,pix',
            'metadata' => 'nullable|array',
        ]);

        try {
            $dto = ProcessPaymentDTO::fromRequest($validated);
            $payment = $this->paymentProcessingService->initiatePayment(
                $invoice,
                $dto,
                auth()->id() ?? '00000000-0000-0000-0000-000000000000'
            );

            return response()->json($payment, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/payments/{payment}/refund - Refund a payment
     */
    public function refund(Payment $payment): JsonResponse
    {
        $this->authorize('refund', $payment);

        try {
            $this->paymentProcessingService->refundPayment(
                $payment,
                auth()->id() ?? '00000000-0000-0000-0000-000000000000'
            );

            return response()->json([
                'message' => 'Estorno processado com sucesso.',
                'payment' => $payment->refresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
