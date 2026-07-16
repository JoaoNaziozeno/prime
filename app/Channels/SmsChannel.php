<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Contracts\Tenant\SmsGatewayInterface;

class SmsChannel
{
    public function __construct(
        protected SmsGatewayInterface $smsGateway
    ) {}

    /**
     * Send the given notification via SMS.
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if ($to && $message) {
            $this->smsGateway->send($to, $message);
        }
    }
}
