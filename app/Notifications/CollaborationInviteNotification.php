<?php

namespace App\Notifications;

use App\Enums\CollaboratorRole;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CollaborationInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public User $inviter,
        public string $role
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roleLabel = CollaboratorRole::tryFrom($this->role)?->label() ?? $this->role;
        $roleDescription = CollaboratorRole::tryFrom($this->role)?->description() ?? '';

        return (new MailMessage)
            ->subject("Invitation à collaborer sur \"{$this->event->title}\"")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$this->inviter->name} vous invite à collaborer sur l'événement \"{$this->event->title}\".")
            ->line("**Rôle proposé :** {$roleLabel}")
            ->line("_{$roleDescription}_")
            ->line('')
            ->line("**Détails de l'événement :**")
            ->line("- **Type :** " . ucfirst($this->event->type))
            ->line("- **Date :** " . ($this->event->date?->format('d/m/Y') ?? 'Non définie'))
            ->line("- **Lieu :** " . ($this->event->location ?? 'Non défini'))
            ->action('Voir l\'invitation', route('events.collaborators.accept', $this->event->id))
            ->line('Vous pouvez accepter ou décliner cette invitation depuis votre espace.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'collaboration_invite',
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'inviter_id' => $this->inviter->id,
            'inviter_name' => $this->inviter->name,
            'role' => $this->role,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'collaboration_invite',
            'title' => 'Invitation à collaborer',
            'message' => "{$this->inviter->name} vous invite à collaborer sur \"{$this->event->title}\".",
            'event_id' => $this->event->id,
            'inviter_id' => $this->inviter->id,
        ];
    }
}
