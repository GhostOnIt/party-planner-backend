<?php

namespace App\Mail;

use App\Models\EventCreationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventCreatedForYouPendingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use \App\Mail\Concerns\EmbedsMailLogo;

    public function __construct(
        public EventCreationInvitation $invitation
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Un événement a été créé pour vous : {$this->invitation->event->title}",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $email = urlencode($this->invitation->email);
        $claimUrl = "{$frontendUrl}/event-created-for-you/{$this->invitation->token}?email={$email}";

        return new Content(
            markdown: 'emails.event-created-for-you-pending',
            with: [
                'invitation' => $this->invitation,
                'event' => $this->invitation->event,
                'admin' => $this->invitation->admin,
                'claimUrl' => $claimUrl,
            ],
        );
    }
}
