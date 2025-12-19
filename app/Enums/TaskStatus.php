<?php

namespace App\Enums;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TODO => 'À faire',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED => 'Terminé',
            self::CANCELLED => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::TODO => 'gray',
            self::IN_PROGRESS => 'blue',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TODO => 'circle',
            self::IN_PROGRESS => 'play',
            self::COMPLETED => 'check-circle',
            self::CANCELLED => 'x-circle',
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
