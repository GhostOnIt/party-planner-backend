<?php

namespace App\Mail;

use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhotoUploadMail extends Mailable
{
    use Queueable, SerializesModels;

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
            subject: "Partagez vos photos de {$event->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $event = $this->guest->event;
        $uploadUrl = config('app.frontend_url', config('app.url')) . '/upload-photo/' . $event->id . '/' . $this->guest->photo_upload_token;

        return new Content(
            markdown: 'emails.photo-upload',
            with: [
                'guest' => $this->guest,
                'event' => $event,
                'uploadUrl' => $uploadUrl,
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
