<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\PaymentProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentProcessingService $paymentProcessingService
    ) {}

    /**
     * POST /api/payments/webhook/{gateway} - Webhook receiver
     */
    public function handle(Request $request, string $gateway): JsonResponse
    {
        $payload = $request->all();

        // Extract transaction ID and event status depending on the gateway
        $transactionId = $payload['transaction_id'] ?? data_get($payload, 'data.object.id');
        $event = $payload['event'] ?? data_get($payload, 'type') ?? $payload['status'] ?? 'completed';

        if (!$transactionId) {
            return response()->json(['error' => 'Transaction ID não encontrado no payload.'], 400);
        }

        try {
            if (in_array($event, ['completed', 'payment_intent.succeeded', 'paid', 'success'])) {
                $this->paymentProcessingService->confirmPayment($transactionId, $payload);
            } elseif (in_array($event, ['failed', 'payment_intent.payment_failed', 'fail'])) {
                $error = $payload['error_message'] ?? 'Falha reportada pelo gateway via webhook.';
                $this->paymentProcessingService->failPayment($transactionId, $error);
            }

            return response()->json(['status' => 'webhook_processed']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
