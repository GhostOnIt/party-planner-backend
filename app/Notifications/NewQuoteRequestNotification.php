<?php

namespace App\Notifications;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewQuoteRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public QuoteRequest $quoteRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvelle demande de devis Business')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Une nouvelle demande de devis a été soumise par {$this->quoteRequest->contact_name}.")
            ->line("Entreprise: {$this->quoteRequest->company_name}")
            ->line("Code de suivi: {$this->quoteRequest->tracking_code}")
            ->line('Connectez-vous au back-office pour la traiter.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quote_request_new',
            'title' => 'Nouvelle demande Business',
            'message' => "Demande {$this->quoteRequest->tracking_code} à modérer.",
            'quote_request_id' => $this->quoteRequest->id,
            'tracking_code' => $this->quoteRequest->tracking_code,
        ];
    }
}
