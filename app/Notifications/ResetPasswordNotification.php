<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     */
    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

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
        $resetUrl = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->greeting('Bonjour !')
            ->line('Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.')
            ->action('Réinitialiser mon mot de passe', $resetUrl)
            ->line('Ce lien de réinitialisation expirera dans ' . config('auth.passwords.users.expire', 60) . ' minutes.')
            ->line('Si vous n\'avez pas demandé de réinitialisation de mot de passe, aucune action n\'est requise.')
            ->salutation('Cordialement, L\'équipe ' . config('app.name'));
    }

    /**
     * Get the reset URL for the given notifiable.
     */
    protected function resetUrl(object $notifiable): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        return $frontendUrl . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'password_reset',
            'message' => 'Demande de réinitialisation de mot de passe.',
        ];
    }
}
