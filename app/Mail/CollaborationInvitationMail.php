<?php

namespace App\Mail;

use App\Models\Collaborator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CollaborationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use \App\Mail\Concerns\EmbedsMailLogo;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Collaborator $collaborator
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invitation à collaborer sur \"{$this->collaborator->event->title}\"",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Get all roles for the collaborator
        $roleNames = $this->collaborator->getEffectiveRoleNames();
        if (count($roleNames) > 1) {
             $lastRole = array_pop($roleNames);
            $roleLabel = implode(', ', $roleNames) . ' et ' . $lastRole;
        } else {
            $roleLabel = $roleNames[0] ?? 'Collaborateur';
        }

        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $token = $this->collaborator->invitation_token;
        $inviteeEmail = $this->collaborator->user->email ?? null;
        $inviteUrl = $token
            ? "{$frontendUrl}/invite/{$token}" . ($inviteeEmail ? '?email=' . urlencode($inviteeEmail) : '')
            : "{$frontendUrl}/invitations";

        return new Content(
            markdown: 'emails.collaboration-invitation',
            with: [
                'collaborator' => $this->collaborator,
                'event' => $this->collaborator->event,
                'inviter' => $this->collaborator->event->user,
                'invitee' => $this->collaborator->user,
                'roleLabel' => $roleLabel,
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
