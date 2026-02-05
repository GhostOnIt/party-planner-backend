<?php

namespace App\Mail;

use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;
    use \App\Mail\Concerns\EmbedsMailLogo;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Guest $guest
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $event = $this->guest->event;

        return new Envelope(
            subject: "Rappel : Votre réponse est attendue pour {$event->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reminder',
            with: [
                'guest' => $this->guest,
                'event' => $this->guest->event,
                'invitation' => $this->guest->invitation,
                'invitationUrl' => $this->guest->invitation?->public_url,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
