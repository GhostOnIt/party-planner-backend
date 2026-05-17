<?php

namespace App\Notifications;

use App\Models\CustomOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOfferRespondedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CustomOffer $offer) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $quoteRequest = $this->offer->quoteRequest;
        $statusLabel = $this->offer->status === 'accepted' ? 'acceptée' : 'refusée';

        $mail = (new MailMessage)
            ->subject("Offre {$statusLabel} — {$quoteRequest->tracking_code}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le client {$quoteRequest->contact_name} ({$quoteRequest->company_name}) a {$statusLabel} l'offre \"{$this->offer->title}\".")
            ->line("Code de suivi: {$quoteRequest->tracking_code}");

        if ($this->offer->client_response_note) {
            $mail->line("Note du client: {$this->offer->client_response_note}");
        }

        return $mail->line('Connectez-vous au back-office pour la suite.');
    }

    public function toArray(object $notifiable): array
    {
        $statusLabel = $this->offer->status === 'accepted' ? 'acceptée' : 'refusée';

        return [
            'type' => 'custom_offer_responded',
            'title' => "Offre {$statusLabel}",
            'message' => "L'offre \"{$this->offer->title}\" pour la demande {$this->offer->quoteRequest->tracking_code} a été {$statusLabel}.",
            'quote_request_id' => $this->offer->quote_request_id,
            'offer_id' => $this->offer->id,
            'offer_status' => $this->offer->status,
        ];
    }
}
