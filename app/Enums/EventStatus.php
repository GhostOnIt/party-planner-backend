<?php

namespace App\Enums;

enum EventStatus: string
{
    case UPCOMING = 'upcoming';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::UPCOMING => 'À venir',
            self::ONGOING => 'En cours',
            self::COMPLETED => 'Terminé',
            self::CANCELLED => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UPCOMING => 'blue',
            self::ONGOING => 'green',
            self::COMPLETED => 'purple',
            self::CANCELLED => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::UPCOMING => 'calendar',
            self::ONGOING => 'clock',
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
