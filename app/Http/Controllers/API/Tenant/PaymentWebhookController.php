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
        // Fail closed: nothing is processed unless the gateway signature is valid.
        if (!$this->hasValidSignature($request, $gateway)) {
            Log::warning('Payment webhook rejected: invalid or missing signature', [
                'gateway' => $gateway,
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Assinatura inválida.'], 401);
        }

        $payload = $request->all();

        // Extract transaction ID and event status depending on the gateway
        $transactionId = $payload['transaction_id'] ?? data_get($payload, 'data.object.id');
        $event = $payload['event'] ?? data_get($payload, 'type') ?? $payload['status'] ?? null;

        if (!$transactionId) {
            return response()->json(['error' => 'Transaction ID não encontrado no payload.'], 400);
        }

        if (!$event) {
            return response()->json(['error' => 'Evento não informado no payload.'], 400);
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
            Log::error('Payment webhook processing failed', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Não foi possível processar o webhook.'], 400);
        }
    }

    /**
     * Verify the webhook signature using the secret configured for the gateway.
     *
     * - Stripe: "Stripe-Signature: t=<timestamp>,v1=<hmac>" over "<timestamp>.<raw body>".
     * - Others: "X-Webhook-Signature" or "X-Signature": <hex hmac-sha256 of the raw body>.
     * - Asaas: "asaas-access-token" token comparison.
     */
    private function hasValidSignature(Request $request, string $gateway): bool
    {
        $secret = config("services.{$gateway}.webhook_secret");

        if (empty($secret)) {
            return false;
        }

        $rawBody = $request->getContent();

        if ($gateway === 'stripe') {
            $header = (string) $request->header('Stripe-Signature', '');
            $timestamp = null;
            $signatures = [];

            foreach (explode(',', $header) as $part) {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
                if ($key === 't') {
                    $timestamp = $value;
                } elseif ($key === 'v1' && $value) {
                    $signatures[] = $value;
                }
            }

            // Reject missing data and replays older than 5 minutes.
            if (!$timestamp || empty($signatures) || abs(time() - (int) $timestamp) > 300) {
                return false;
            }

            $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    return true;
                }
            }

            return false;
        }

        // Asaas token verification support
        if ($gateway === 'asaas') {
            $asaasToken = (string) $request->header('asaas-access-token', '');
            if ($asaasToken !== '' && hash_equals($secret, $asaasToken)) {
                return true;
            }
        }

        $signature = (string) ($request->header('X-Webhook-Signature') ?: $request->header('X-Signature') ?: '');

        return $signature !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
