<?php

namespace App\Services\Tenant\Gateways;

use App\Contracts\Tenant\SmsGatewayInterface;
use Illuminate\Support\Facades\Log;

class MockSmsGateway implements SmsGatewayInterface
{
    /**
     * Send mock SMS log
     */
    public function send(string $to, string $message): bool
    {
        Log::info("SMS SEND MOCK [To: {$to}] - Message: {$message}");
        return true;
    }
}
