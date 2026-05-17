<?php

namespace App\Notifications;

use App\Models\CustomOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomOfferCreatedNotification extends Notification implements ShouldQueue
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
        $formattedPrice = number_format($this->offer->price_amount, 0, ',', ' ') . ' ' . $this->offer->price_currency;
        $publicUrl = config('app.frontend_url', config('app.url')) . '/offers/' . $this->offer->client_token;

        return (new MailMessage)
            ->subject('Votre offre personnalisée est prête')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Suite à votre demande de devis ({$quoteRequest->tracking_code}), nous avons préparé une offre personnalisée pour vous.")
            ->line("**{$this->offer->title}**")
            ->line("Montant: {$formattedPrice}")
            ->line("Validité: {$this->offer->validity_days} jours")
            ->action('Consulter l\'offre', $publicUrl)
            ->line('Vous pouvez accepter ou refuser cette offre en cliquant sur le lien ci-dessus.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'custom_offer_created',
            'title' => 'Offre personnalisée reçue',
            'message' => "Une offre personnalisée \"{$this->offer->title}\" a été créée pour votre demande {$this->offer->quoteRequest->tracking_code}.",
            'quote_request_id' => $this->offer->quote_request_id,
            'offer_id' => $this->offer->id,
            'client_token' => $this->offer->client_token,
        ];
    }
}
