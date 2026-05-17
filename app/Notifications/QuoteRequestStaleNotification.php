<?php

namespace App\Notifications;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteRequestStaleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public QuoteRequest $quoteRequest, public int $daysSinceLastActivity) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Demande Business sans activité depuis {$this->daysSinceLastActivity} jours")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("La demande {$this->quoteRequest->tracking_code} ({$this->quoteRequest->company_name}) est sans activité depuis {$this->daysSinceLastActivity} jours.")
            ->line('Pensez à relancer le client ou à mettre à jour son statut.')
            ->action('Ouvrir la demande', config('app.frontend_url', config('app.url')) . '/admin/quote-requests/' . $this->quoteRequest->id);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quote_request_stale',
            'title' => 'Demande Business inactive',
            'message' => "Demande {$this->quoteRequest->tracking_code} sans activité depuis {$this->daysSinceLastActivity} jours.",
            'quote_request_id' => $this->quoteRequest->id,
            'tracking_code' => $this->quoteRequest->tracking_code,
            'days_since_last_activity' => $this->daysSinceLastActivity,
        ];
    }
}
