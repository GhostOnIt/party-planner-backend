<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BudgetAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public float $percentage,
        public string $alertType = 'threshold'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->greeting("Bonjour {$notifiable->name},");

        if ($this->alertType === 'over_budget') {
            $message
                ->subject("⚠️ Dépassement de budget pour \"{$this->event->title}\"")
                ->line("Attention ! Vous avez dépassé votre budget pour l'événement \"{$this->event->title}\".")
                ->line("Vous êtes actuellement à **{$this->percentage}%** de votre budget estimé.");
        } else {
            $message
                ->subject("Alerte budget : {$this->percentage}% utilisé pour \"{$this->event->title}\"")
                ->line("Vous avez utilisé **{$this->percentage}%** de votre budget pour l'événement \"{$this->event->title}\".")
                ->line("Il est peut-être temps de revoir vos dépenses.");
        }

        return $message
            ->action('Voir le budget', route('events.budget.index', $this->event->id))
            ->line('Restez maître de vos dépenses !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'budget_alert',
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'percentage' => $this->percentage,
            'alert_type' => $this->alertType,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = $this->alertType === 'over_budget'
            ? "Dépassement de budget pour \"{$this->event->title}\" ({$this->percentage}%)"
            : "Vous avez utilisé {$this->percentage}% du budget pour \"{$this->event->title}\"";

        return [
            'type' => 'budget_alert',
            'title' => 'Alerte budget',
            'message' => $message,
            'event_id' => $this->event->id,
        ];
    }
}
