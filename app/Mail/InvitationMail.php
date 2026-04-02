<?php

namespace App\Mail;

use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;
    use \App\Mail\Concerns\EmbedsMailLogo;

    /**
     * Binaire + métadonnées pour intégration email (CID), pas data-URI : Gmail / Outlook
     * bloquent souvent les images en src="data:image/...".
     *
     * @return array{binary: string, mime: string, filename: string}|null
     */
    private function generateCheckInQrForEmail(string $checkInUrl): ?array
    {
        if (!class_exists(\Endroid\QrCode\QrCode::class)) {
            return null;
        }

        $makeQr = fn (): \Endroid\QrCode\QrCode => new \Endroid\QrCode\QrCode(
            data: $checkInUrl,
            encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
            errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::High,
            size: 240,
            margin: 10,
            roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::Margin,
            foregroundColor: new \Endroid\QrCode\Color\Color(0, 0, 0),
            backgroundColor: new \Endroid\QrCode\Color\Color(255, 255, 255),
        );

        try {
            if (class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($makeQr());

                return [
                    'binary' => $result->getString(),
                    'mime' => $result->getMimeType(),
                    'filename' => 'checkin-qr.png',
                ];
            }
        } catch (\Throwable) {
            // Fallback SVG
        }

        try {
            if (class_exists(\Endroid\QrCode\Writer\SvgWriter::class)) {
                $writer = new \Endroid\QrCode\Writer\SvgWriter();
                $result = $writer->write($makeQr());

                return [
                    'binary' => $result->getString(),
                    'mime' => $result->getMimeType(),
                    'filename' => 'checkin-qr.svg',
                ];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Guest $guest,
        public Invitation $invitation
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $event = $this->guest->event;

        return new Envelope(
            subject: "Vous êtes invité(e) à {$event->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $checkInUrl = $frontendUrl . '/check-in/' . $this->guest->invitation_token;
        $checkInQrEmbedded = $this->generateCheckInQrForEmail($checkInUrl);

        return new Content(
            markdown: 'emails.invitation',
            with: [
                'guest' => $this->guest,
                'event' => $this->guest->event,
                'invitation' => $this->invitation,
                'invitationUrl' => $this->invitation->public_url,
                'checkInUrl' => $checkInUrl,
                'checkInQrEmbedded' => $checkInQrEmbedded,
                'customMessage' => $this->invitation->custom_message,
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
