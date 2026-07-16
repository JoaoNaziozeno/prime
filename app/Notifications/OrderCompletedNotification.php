<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\NotificationPreference;
use App\Channels\SmsChannel;

class OrderCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected OrderOfService $order
    ) {}

    /**
     * Determine notification channels based on user preferences.
     */
    public function via($notifiable): array
    {
        $channels = NotificationPreference::getEnabledChannels($notifiable->id, 'order_completed');
        
        return array_map(function ($channel) {
            return $channel === 'sms' ? SmsChannel::class : $channel;
        }, $channels);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ordem de Serviço Finalizada - OS #{$this->order->reference_number}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("A Ordem de Serviço #{$this->order->reference_number} para o veículo de placa {$this->order->vehicle->plate} foi concluída com sucesso.")
            ->line("Valor Final: R$ " . number_format($this->order->actual_cost ?? $this->order->estimated_cost, 2, ',', '.'))
            ->action('Visualizar OS', url("/tenant/orders/{$this->order->id}"))
            ->line('Agradecemos a preferência!');
    }

    /**
     * Get the array representation of the notification (Database Channel).
     */
    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'reference_number' => $this->order->reference_number,
            'vehicle_plate' => $this->order->vehicle->plate ?? 'N/A',
            'title' => 'Ordem de Serviço Concluída',
            'message' => "A OS #{$this->order->reference_number} foi concluída com sucesso.",
            'amount' => $this->order->actual_cost ?? $this->order->estimated_cost,
        ];
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms($notifiable): string
    {
        return "Prime ERP: Ola {$notifiable->name}, a sua OS #{$this->order->reference_number} foi concluida com sucesso.";
    }
}
