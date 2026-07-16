<?php

namespace App\Contracts\Tenant;

interface SmsGatewayInterface
{
    /**
     * Send SMS notification
     */
    public function send(string $to, string $message): bool;
}
