<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public string $exportType,
        public string $format,
        public string $filePath
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeLabels = [
            'guests' => 'des invités',
            'budget' => 'du budget',
            'tasks' => 'des tâches',
            'report' => 'complet',
        ];

        $typeLabel = $typeLabels[$this->exportType] ?? $this->exportType;

        return (new MailMessage)
            ->subject("Export {$typeLabel} prêt - {$this->event->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre export {$typeLabel} pour l'événement \"{$this->event->title}\" est prêt.")
            ->line("Format : " . strtoupper($this->format))
            ->action('Télécharger l\'export', route('exports.download', ['path' => $this->filePath]))
            ->line('Ce lien expirera dans 24 heures.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'export_ready',
            'export_type' => $this->exportType,
            'format' => $this->format,
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'file_path' => $this->filePath,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $typeLabels = [
            'guests' => 'des invités',
            'budget' => 'du budget',
            'tasks' => 'des tâches',
            'report' => 'complet',
        ];

        $typeLabel = $typeLabels[$this->exportType] ?? $this->exportType;

        return [
            'type' => 'export_ready',
            'title' => 'Export prêt',
            'message' => "L'export {$typeLabel} pour \"{$this->event->title}\" est prêt à être téléchargé.",
            'export_type' => $this->exportType,
            'format' => $this->format,
            'event_id' => $this->event->id,
            'file_path' => $this->filePath,
        ];
    }
}
