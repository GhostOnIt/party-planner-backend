<?php

namespace App\Notifications;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuoteRequestUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public QuoteRequest $quoteRequest,
        public string $title,
        public string $message
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message)
            ->line("Code de suivi: {$this->quoteRequest->tracking_code}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quote_request_updated',
            'title' => $this->title,
            'message' => $this->message,
            'quote_request_id' => $this->quoteRequest->id,
            'tracking_code' => $this->quoteRequest->tracking_code,
        ];
    }
}
