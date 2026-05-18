<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PilotFeedbackMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $feedbackBody
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Party Planner — Feedback] '.$this->user->email,
            replyTo: [
                new Address($this->user->email, $this->user->name),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.pilot-feedback',
            with: [
                'userName' => $this->user->name,
                'userEmail' => $this->user->email,
                'body' => $this->feedbackBody,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
