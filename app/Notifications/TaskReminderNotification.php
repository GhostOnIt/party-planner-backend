<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public int $daysUntilDue
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueText = $this->daysUntilDue === 0 ? "aujourd'hui" : "dans {$this->daysUntilDue} jour(s)";

        return (new MailMessage)
            ->subject("Rappel : Tâche \"{$this->task->title}\" à échéance")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("La tâche \"{$this->task->title}\" pour l'événement \"{$this->task->event->title}\" est due {$dueText}.")
            ->line("**Description :** " . ($this->task->description ?? 'Aucune description'))
            ->line("**Priorité :** " . ucfirst($this->task->priority))
            ->action('Voir la tâche', route('events.tasks.index', $this->task->event_id))
            ->line('N\'oubliez pas de la terminer à temps !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_reminder',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'event_id' => $this->task->event_id,
            'event_title' => $this->task->event->title,
            'days_until_due' => $this->daysUntilDue,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $dueText = $this->daysUntilDue === 0 ? "aujourd'hui" : "dans {$this->daysUntilDue} jour(s)";

        return [
            'type' => 'task_reminder',
            'title' => 'Rappel de tâche',
            'message' => "La tâche \"{$this->task->title}\" pour \"{$this->task->event->title}\" est due {$dueText}.",
            'task_id' => $this->task->id,
            'event_id' => $this->task->event_id,
        ];
    }
}
