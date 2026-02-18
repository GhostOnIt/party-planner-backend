<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventCreatedForUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public User $admin
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $eventPath = '/events/' . $this->event->id;
        // Use login URL with redirect and email prefill so user lands on login (or gets redirected to event if already logged in)
        $actionUrl = $frontendUrl . '/login?redirect=' . urlencode($eventPath) . '&email=' . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject("Un événement a été créé pour vous : {$this->event->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("L'administrateur {$this->admin->name} a créé un événement pour vous sur la plateforme.")
            ->line("**Événement :** {$this->event->title}")
            ->line("- **Type :** " . ucfirst($this->event->type))
            ->line("- **Date :** " . ($this->event->date ? \Carbon\Carbon::parse($this->event->date)->format('d/m/Y') : 'Non définie'))
            ->line("- **Lieu :** " . ($this->event->location ?? 'Non défini'))
            ->action('Voir l\'événement', $actionUrl)
            ->line('Vous pouvez gérer cet événement depuis votre espace.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'event_created_for_user',
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'admin_id' => $this->admin->id,
            'admin_name' => $this->admin->name,
        ];
    }
}
