<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Tenant\Driver;
use App\Models\Tenant\NotificationPreference;
use App\Channels\SmsChannel;

class CnhExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Driver $driver
    ) {}

    /**
     * Determine notification channels based on user preferences.
     */
    public function via($notifiable): array
    {
        $channels = NotificationPreference::getEnabledChannels($notifiable->id, 'cnh_expiring');
        
        return array_map(function ($channel) {
            return $channel === 'sms' ? SmsChannel::class : $channel;
        }, $channels);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $days = $this->driver->getDaysUntilCnhExpiration();
        $status = $this->driver->isCnhExpired() ? 'vencida' : "a vencer em {$days} dias";

        return (new MailMessage)
            ->subject("Alerta de Vencimento de CNH - {$this->driver->name}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Identificamos que a habilitação (CNH) do motorista {$this->driver->name} está {$status}.")
            ->line("Data de Vencimento: " . $this->driver->cnh_expiration->toDateString())
            ->line("Número CNH: {$this->driver->cnh}")
            ->action('Visualizar Motorista', url("/tenant/drivers/{$this->driver->id}"));
    }

    /**
     * Get the array representation of the notification (Database Channel).
     */
    public function toArray($notifiable): array
    {
        return [
            'driver_id' => $this->driver->id,
            'driver_name' => $this->driver->name,
            'cnh' => $this->driver->cnh,
            'expiration_date' => $this->driver->cnh_expiration->toDateString(),
            'title' => 'Alerta de CNH Vencendo',
            'message' => "A CNH do motorista {$this->driver->name} está próxima do vencimento.",
        ];
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms($notifiable): string
    {
        $days = $this->driver->getDaysUntilCnhExpiration();
        return "Prime ERP: Alerta! A CNH do motorista {$this->driver->name} vence em {$days} dias (Data: " . $this->driver->cnh_expiration->toDateString() . ").";
    }
}
