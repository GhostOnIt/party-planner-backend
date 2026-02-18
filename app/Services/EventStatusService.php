<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;

class EventStatusService
{
    /**
     * Check if an event can transition to the given status (manual change rules).
     *
     * Rules:
     * - → ongoing: only from 24h before event scheduled time
     * - → completed: only after event scheduled time has passed
     * - → cancelled: at any time
     * - → upcoming: allowed before event time (to revert a mistake)
     */
    public function canTransitionTo(Event $event, EventStatus $newStatus): bool
    {
        $eventStart = $this->getEventStart($event);
        $now = now();

        return match ($newStatus) {
            EventStatus::ONGOING => $now->gte($eventStart->copy()->subHours(24)),
            EventStatus::COMPLETED => $now->gte($eventStart),
            EventStatus::CANCELLED => true,
            EventStatus::UPCOMING => $now->lt($eventStart),
        };
    }

    /**
     * Get validation error message when transition is not allowed.
     */
    public function getTransitionErrorMessage(Event $event, EventStatus $newStatus): string
    {
        $eventStart = $this->getEventStart($event);
        $opensAt = $eventStart->copy()->subHours(24);

        return match ($newStatus) {
            EventStatus::ONGOING => "Le statut \"En cours\" ne peut être défini qu'à partir de 24h avant l'événement ({$opensAt->format('d/m/Y H:i')}).",
            EventStatus::COMPLETED => "Le statut \"Terminé\" ne peut être défini qu'après l'heure prévue de l'événement ({$eventStart->format('d/m/Y H:i')}).",
            EventStatus::CANCELLED => '',
            EventStatus::UPCOMING => "Le statut \"À venir\" ne peut être défini qu'avant l'heure prévue de l'événement ({$eventStart->format('d/m/Y H:i')}).",
        };
    }

    /**
     * Get the event start datetime (date + time, or start of day if no time).
     */
    public function getEventStart(Event $event): \Carbon\Carbon
    {
        $date = $event->date;
        if (!$date) {
            return now()->addYear(); // Far future = no constraint
        }

        $timeStr = $event->time ? $event->time->format('H:i') : '00:00';

        return $date->copy()->setTimeFromTimeString($timeStr);
    }
}
