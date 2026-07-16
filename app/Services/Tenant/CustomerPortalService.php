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
    public function generateMagicLink(string $email): string
    {
        return DB::connection('tenant')->transaction(function () use ($email) {
            $customer = Customer::where('email', $email)
                ->where('status', Customer::STATUS_ACTIVE)
                ->firstOrFail();

            $token = Str::random(60);

            CustomerLoginToken::create([
                'customer_id' => $customer->id,
                'token' => $token,
                'expires_at' => now()->addMinutes(30),
            ]);

            return $token;
        });
    }

    public function authenticate(string $token): ?Customer
    {
        return DB::connection('tenant')->transaction(function () use ($token) {
            $loginToken = CustomerLoginToken::where('token', $token)->first();

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
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $message,
        ]);
    }
}
