<?php

namespace App\Enums;

enum PhotoType: string
{
    case MOODBOARD = 'moodboard';
    case EVENT_PHOTO = 'event_photo';

    public function label(): string
    {
        return match ($this) {
            self::MOODBOARD => 'Moodboard',
            self::EVENT_PHOTO => 'Photo événement',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MOODBOARD => 'Images d\'inspiration pour la planification',
            self::EVENT_PHOTO => 'Photos prises pendant l\'événement',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MOODBOARD => 'sparkles',
            self::EVENT_PHOTO => 'camera',
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
