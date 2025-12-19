<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Vérifiez votre adresse email')
            ->greeting('Bonjour ' . $notifiable->name . ' !')
            ->line('Merci de vous être inscrit sur ' . config('app.name') . '.')
            ->line('Veuillez cliquer sur le bouton ci-dessous pour vérifier votre adresse email.')
            ->action('Vérifier mon email', $verificationUrl)
            ->line('Ce lien expirera dans 60 minutes.')
            ->line('Si vous n\'avez pas créé de compte, aucune action n\'est requise.')
            ->salutation('Cordialement, L\'équipe ' . config('app.name'));
    }

    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl(object $notifiable): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        // Generate a signed URL for the API
        $signedUrl = URL::temporarySignedRoute(
            'api.verify-email',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            false
        );

        // Extract query parameters from signed URL
        $parsedUrl = parse_url($signedUrl);
        $queryString = $parsedUrl['query'] ?? '';

        // Build frontend URL with verification parameters
        return $frontendUrl . '/verify-email/' . $notifiable->getKey() . '/' . sha1($notifiable->getEmailForVerification()) . '?' . $queryString;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'email_verification',
            'message' => 'Veuillez vérifier votre adresse email.',
        ];
    }
}
