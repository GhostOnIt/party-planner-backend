<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public int $daysUntilEvent
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueText = $this->daysUntilEvent === 0 ? "aujourd'hui" : "dans {$this->daysUntilEvent} jour(s)";

        $message = (new MailMessage)
            ->subject("🎉 Rappel : \"{$this->event->title}\" a lieu {$dueText}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre événement \"{$this->event->title}\" a lieu {$dueText} !");

        if ($this->event->location) {
            $message->line("**Lieu :** {$this->event->location}");
        }

        if ($this->event->time) {
            $message->line("**Heure :** {$this->event->time}");
        }

        // Add summary stats
        $guestsConfirmed = $this->event->guests()->where('rsvp_status', 'accepted')->count();
        $tasksPending = $this->event->tasks()->where('status', '!=', 'completed')->count();

        $message
            ->line("**Invités confirmés :** {$guestsConfirmed}")
            ->line("**Tâches en cours :** {$tasksPending}");

        return $message
            ->action('Voir l\'événement', route('events.show', $this->event->id))
            ->line('Dernière ligne droite avant le grand jour !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'event_reminder',
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'days_until_event' => $this->daysUntilEvent,
            'event_date' => $this->event->date?->toDateString(),
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $dueText = $this->daysUntilEvent === 0 ? "aujourd'hui" : "dans {$this->daysUntilEvent} jour(s)";

        return [
            'type' => 'event_reminder',
            'title' => 'Rappel événement',
            'message' => "Votre événement \"{$this->event->title}\" a lieu {$dueText}.",
            'event_id' => $this->event->id,
        ];
    }
}
