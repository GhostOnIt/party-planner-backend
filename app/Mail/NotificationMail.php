<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use \App\Mail\Concerns\EmbedsMailLogo;

    public function __construct(
        public Notification $notification
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.notification',
            with: [
                'notification' => $this->notification,
                'user' => $this->notification->user,
                'event' => $this->notification->event,
                'actionUrl' => $this->getActionUrl(),
            ],
        );
    }

    protected function getActionUrl(): ?string
    {
        if ($this->notification->event_id) {
            return route('events.show', $this->notification->event_id);
        }

        return route('notifications.index');
    }

    public function attachments(): array
    {
        return [];
    }
}
