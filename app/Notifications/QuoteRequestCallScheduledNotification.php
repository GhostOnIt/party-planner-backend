<?php

namespace App\Notifications;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteRequestCallScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public QuoteRequest $quoteRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dateLabel = $this->quoteRequest->call_scheduled_at?->format('d/m/Y H:i') ?? 'à confirmer';

        return (new MailMessage)
            ->subject('Votre call Business est planifié')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre call de qualification a été planifié le {$dateLabel}.")
            ->line("Code de suivi: {$this->quoteRequest->tracking_code}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quote_request_call_scheduled',
            'title' => 'Call Business planifié',
            'message' => 'Votre call de qualification a été planifié.',
            'quote_request_id' => $this->quoteRequest->id,
            'tracking_code' => $this->quoteRequest->tracking_code,
            'call_scheduled_at' => optional($this->quoteRequest->call_scheduled_at)->toISOString(),
        ];
    }
}
