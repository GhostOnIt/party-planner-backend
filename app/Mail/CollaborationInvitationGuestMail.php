<?php

namespace App\Mail;

use App\Models\CollaborationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CollaborationInvitationGuestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use \App\Mail\Concerns\EmbedsMailLogo;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public CollaborationInvitation $invitation
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invitation à collaborer sur \"{$this->invitation->event->title}\"",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $email = urlencode($this->invitation->email);
        $inviteUrl = "{$frontendUrl}/invite/{$this->invitation->token}?email={$email}";

        return new Content(
            markdown: 'emails.collaboration-invitation-guest',
            with: [
                'invitation' => $this->invitation,
                'event' => $this->invitation->event,
                'inviter' => $this->invitation->event->user,
                'roleLabel' => $this->invitation->getRoleLabel(),
                'inviteUrl' => $inviteUrl,
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
