<?php

namespace App\Enums;

enum EventStatus: string
{
    case DRAFT = 'draft';
    case PLANNING = 'planning';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::PLANNING => 'En planification',
            self::CONFIRMED => 'Confirmé',
            self::COMPLETED => 'Terminé',
            self::CANCELLED => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PLANNING => 'blue',
            self::CONFIRMED => 'green',
            self::COMPLETED => 'purple',
            self::CANCELLED => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DRAFT => 'pencil',
            self::PLANNING => 'clock',
            self::CONFIRMED => 'check-circle',
            self::COMPLETED => 'flag',
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
