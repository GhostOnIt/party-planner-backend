<?php

namespace App\Enums;

enum EventType: string
{
    case MARIAGE = 'mariage';
    case ANNIVERSAIRE = 'anniversaire';
    case BABY_SHOWER = 'baby_shower';
    case SOIREE = 'soiree';
    case BRUNCH = 'brunch';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::MARIAGE => 'Mariage',
            self::ANNIVERSAIRE => 'Anniversaire',
            self::BABY_SHOWER => 'Baby Shower',
            self::SOIREE => 'Soirée',
            self::BRUNCH => 'Brunch',
            self::AUTRE => 'Autre',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MARIAGE => 'heart',
            self::ANNIVERSAIRE => 'cake',
            self::BABY_SHOWER => 'baby',
            self::SOIREE => 'music',
            self::BRUNCH => 'coffee',
            self::AUTRE => 'calendar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MARIAGE => 'pink',
            self::ANNIVERSAIRE => 'purple',
            self::BABY_SHOWER => 'blue',
            self::SOIREE => 'yellow',
            self::BRUNCH => 'orange',
            self::AUTRE => 'gray',
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
