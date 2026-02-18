<?php

namespace App\Mail;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventStatusChangeMail extends Mailable
{
    use Queueable, SerializesModels;
    use \App\Mail\Concerns\EmbedsMailLogo;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Guest $guest,
        public Event $event,
        public EventStatus $status
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->status) {
            EventStatus::CANCELLED => "Annulation : {$this->event->title}",
            EventStatus::ONGOING => "L'événement {$this->event->title} a commencé",
            EventStatus::COMPLETED => "L'événement {$this->event->title} est terminé",
            EventStatus::UPCOMING => "Mise à jour : {$this->event->title}",
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.event-status-change',
            with: [
                'guest' => $this->guest,
                'event' => $this->event,
                'status' => $this->status,
                'invitationUrl' => $this->guest->invitation?->public_url,
            ],
        );
    }
}
