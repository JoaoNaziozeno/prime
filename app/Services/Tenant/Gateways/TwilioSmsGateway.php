<?php

namespace App\Services\Tenant\Gateways;

use App\Contracts\Tenant\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioSmsGateway implements SmsGatewayInterface
{
    /**
     * Send an SMS using the Twilio API.
     */
    public function send(string $to, string $message): bool
    {
        $sid = config('services.twilio.sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.from');

        if (!$sid || !$authToken || !$from) {
            // Log fallback/warning if credentials are missing
            Log::warning("Twilio SMS credentials missing (SID, token or from number). Fallback to Mock log -> To: {$to}, Msg: {$message}");
            return true;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        
        try {
            $response = Http::withBasicAuth($sid, $authToken)
                ->asForm()
                ->post($url, [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                Log::info("Twilio SMS sent successfully to {$to}");
                return true;
            }

            Log::error("Failed to send Twilio SMS to {$to}. Status: {$response->status()}, Response: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Exception occurred while sending Twilio SMS to {$to}: " . $e->getMessage());
            return false;
        }
    }
}
