<?php

namespace App\Enums;

enum NotificationType: string
{
    case TASK_REMINDER = 'task_reminder';
    case GUEST_REMINDER = 'guest_reminder';
    case BUDGET_ALERT = 'budget_alert';
    case EVENT_REMINDER = 'event_reminder';
    case COLLABORATION_INVITE = 'collaboration_invite';

    public function label(): string
    {
        return match ($this) {
            self::TASK_REMINDER => 'Rappel de tâche',
            self::GUEST_REMINDER => 'Rappel invité',
            self::BUDGET_ALERT => 'Alerte budget',
            self::EVENT_REMINDER => 'Rappel événement',
            self::COLLABORATION_INVITE => 'Invitation collaboration',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TASK_REMINDER => 'clipboard-list',
            self::GUEST_REMINDER => 'users',
            self::BUDGET_ALERT => 'currency-dollar',
            self::EVENT_REMINDER => 'calendar',
            self::COLLABORATION_INVITE => 'user-plus',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TASK_REMINDER => 'blue',
            self::GUEST_REMINDER => 'green',
            self::BUDGET_ALERT => 'red',
            self::EVENT_REMINDER => 'purple',
            self::COLLABORATION_INVITE => 'yellow',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->label()
        ])->toArray();
    }
}
