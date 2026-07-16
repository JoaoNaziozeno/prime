<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Payment;
use App\Models\Tenant\NotificationPreference;
use App\Channels\SmsChannel;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Invoice $invoice,
        protected Payment $payment
    ) {}

    /**
     * Determine notification channels based on user preferences.
     */
    public function via($notifiable): array
    {
        $channels = NotificationPreference::getEnabledChannels($notifiable->id, 'payment_received');
        
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
            ->subject("Confirmação de Pagamento - Fatura #{$this->invoice->invoice_number}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Confirmamos o recebimento do pagamento da Fatura #{$this->invoice->invoice_number}.")
            ->line("Valor Pago: R$ " . number_format($this->payment->amount, 2, ',', '.'))
            ->line("Meio de Pagamento: " . strtoupper($this->payment->paymentMethod->name ?? 'N/A'))
            ->line("Status da Fatura: " . strtoupper($this->invoice->status))
            ->action('Visualizar Fatura', url("/tenant/invoices/{$this->invoice->id}"));
    }

    /**
     * Get the array representation of the notification (Database Channel).
     */
    public function toArray($notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'payment_id' => $this->payment->id,
            'amount_paid' => $this->payment->amount,
            'title' => 'Pagamento Confirmado',
            'message' => "Recebemos o pagamento de R$ " . number_format($this->payment->amount, 2, ',', '.') . " para a fatura #{$this->invoice->invoice_number}.",
        ];
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms($notifiable): string
    {
        return "Prime ERP: Confirmamos o pagamento de R$ " . number_format($this->payment->amount, 2, ',', '.') . " para a Fatura #{$this->invoice->invoice_number}.";
    }
}
