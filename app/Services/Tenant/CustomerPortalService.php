<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerLoginToken;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CustomerPortalService
{
    /**
     * Generate a magic-link token, store only its SHA-256 hash in the DB,
     * and return the raw token so the caller can deliver it via e-mail / WhatsApp.
     */
    public function generateMagicLink(string $email): string
    {
        return DB::connection('tenant')->transaction(function () use ($email) {
            $customer = Customer::where('email', $email)
                ->where('status', Customer::STATUS_ACTIVE)
                ->firstOrFail();

            // Invalidate any still-valid token for this customer
            CustomerLoginToken::where('customer_id', $customer->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['used_at' => now()]);

            $rawToken = Str::random(64);

            CustomerLoginToken::create([
                'customer_id' => $customer->id,
                'token'       => hash('sha256', $rawToken),
                'expires_at'  => now()->addMinutes(30),
            ]);

            return $rawToken;
        });
    }

    /**
     * Validate the raw token by comparing its hash and authenticate the customer.
     */
    public function authenticate(string $rawToken): ?Customer
    {
        return DB::connection('tenant')->transaction(function () use ($rawToken) {
            $hash = hash('sha256', $rawToken);

            $loginToken = CustomerLoginToken::where('token', $hash)->first();

            if (!$loginToken || !$loginToken->isValid()) {
                return null;
            }

            $loginToken->update([
                'used_at' => now(),
            ]);

            return $loginToken->customer;
        });
    }

    public function sendMessage(OrderOfService $order, string $senderType, string $senderId, string $message): OrderMessage
    {
        if (!in_array($senderType, ['user', 'customer'])) {
            throw new InvalidArgumentException('Tipo de remetente inválido.');
        }

        if (empty(trim($message))) {
            throw new InvalidArgumentException('A mensagem não pode ser vazia.');
        }

        return OrderMessage::create([
            'order_of_service_id' => $order->id,
            'sender_type'         => $senderType,
            'sender_id'           => $senderId,
            'message'             => $message,
        ]);
    }
}
