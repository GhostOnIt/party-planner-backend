<?php

namespace App\Notifications;

use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Guest $guest,
        public int $pendingCount = 1
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Rappel : Réponses en attente pour \"{$this->guest->event->title}\"")
            ->greeting("Bonjour {$notifiable->name},");

        if ($this->pendingCount > 1) {
            $message->line("Vous avez {$this->pendingCount} invités en attente de réponse pour votre événement \"{$this->guest->event->title}\".");
        } else {
            $message->line("{$this->guest->name} n'a pas encore répondu à l'invitation pour \"{$this->guest->event->title}\".");
        }

        return $message
            ->action('Gérer les invités', route('events.guests.index', $this->guest->event_id))
            ->line('Pensez à relancer les invités qui n\'ont pas répondu.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'guest_reminder',
            'guest_id' => $this->guest->id,
            'guest_name' => $this->guest->name,
            'event_id' => $this->guest->event_id,
            'event_title' => $this->guest->event->title,
            'pending_count' => $this->pendingCount,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'guest_reminder',
            'title' => 'Rappel RSVP en attente',
            'message' => "{$this->guest->name} n'a pas encore répondu pour \"{$this->guest->event->title}\".",
            'guest_id' => $this->guest->id,
            'event_id' => $this->guest->event_id,
        ];
    }
}
