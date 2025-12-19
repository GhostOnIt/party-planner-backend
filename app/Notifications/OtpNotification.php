<?php

namespace App\Notifications;

use App\Models\Otp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Otp $otp
    ) {}

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
        $appName = config('app.name', 'Party Planner');
        $typeLabel = $this->getTypeLabel();
        $subject = $this->getSubject();

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Bonjour !')
            ->line("Voici votre code de {$typeLabel} :")
            ->line("**{$this->otp->code}**")
            ->line("Ce code expire dans " . Otp::EXPIRATION_MINUTES . " minutes.")
            ->line("Si vous n'avez pas demandé ce code, ignorez cet email.")
            ->salutation("Cordialement, L'équipe {$appName}");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'otp',
            'otp_type' => $this->otp->type,
            'message' => "Code OTP envoyé pour {$this->getTypeLabel()}.",
        ];
    }

    /**
     * Get human-readable type label.
     */
    protected function getTypeLabel(): string
    {
        return match ($this->otp->type) {
            Otp::TYPE_REGISTRATION => 'vérification de compte',
            Otp::TYPE_LOGIN => 'connexion',
            Otp::TYPE_PASSWORD_RESET => 'réinitialisation de mot de passe',
            default => 'vérification',
        };
    }

    /**
     * Get email subject based on OTP type.
     */
    protected function getSubject(): string
    {
        $appName = config('app.name', 'Party Planner');

        return match ($this->otp->type) {
            Otp::TYPE_REGISTRATION => "{$appName} - Vérifiez votre compte",
            Otp::TYPE_LOGIN => "{$appName} - Code de connexion",
            Otp::TYPE_PASSWORD_RESET => "{$appName} - Réinitialisation de mot de passe",
            default => "{$appName} - Votre code de vérification",
        };
    }
}
