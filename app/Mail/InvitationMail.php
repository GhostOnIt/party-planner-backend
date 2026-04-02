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

    private function generateCheckInQrDataUri(string $checkInUrl): ?string
    {
        // La lib `endroid/qr-code` sera dispo en production. En dev, on tolère l'absence
        // de la dépendance ou de certaines extensions (ex: GD) en retournant `null`.
        if (!class_exists(\Endroid\QrCode\QrCode::class)) {
            return null;
        }

        try {
            // 1) PNG (le plus compatible mail clients) si disponible
            if (class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
                $qrCode = new \Endroid\QrCode\QrCode(
                    data: $checkInUrl,
                    encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
                    errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::High,
                    size: 240,
                    margin: 10,
                    roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::Margin,
                    foregroundColor: new \Endroid\QrCode\Color\Color(0, 0, 0),
                    backgroundColor: new \Endroid\QrCode\Color\Color(255, 255, 255),
                );

                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);

                return $result->getDataUri();
            }
        } catch (\Throwable) {
            // Fallback en-dessous (SVG)
        }

        try {
            // 2) SVG (utile même sans extension GD)
            if (class_exists(\Endroid\QrCode\Writer\SvgWriter::class)) {
                $qrCode = new \Endroid\QrCode\QrCode(
                    data: $checkInUrl,
                    encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
                    errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::High,
                    size: 240,
                    margin: 10,
                    roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::Margin,
                    foregroundColor: new \Endroid\QrCode\Color\Color(0, 0, 0),
                    backgroundColor: new \Endroid\QrCode\Color\Color(255, 255, 255),
                );

                $writer = new \Endroid\QrCode\Writer\SvgWriter();
                $result = $writer->write($qrCode);

                return $result->getDataUri();
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
        $checkInQrDataUri = $this->generateCheckInQrDataUri($checkInUrl);

        return new Content(
            markdown: 'emails.invitation',
            with: [
                'guest' => $this->guest,
                'event' => $this->guest->event,
                'invitation' => $this->invitation,
                'invitationUrl' => $this->invitation->public_url,
                'checkInUrl' => $checkInUrl,
                'checkInQrDataUri' => $checkInQrDataUri,
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
