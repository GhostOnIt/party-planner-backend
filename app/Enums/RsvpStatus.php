<?php

namespace App\Enums;

enum RsvpStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case MAYBE = 'maybe';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::ACCEPTED => 'Confirmé',
            self::DECLINED => 'Décliné',
            self::MAYBE => 'Peut-être',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::ACCEPTED => 'green',
            self::DECLINED => 'red',
            self::MAYBE => 'blue',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'clock',
            self::ACCEPTED => 'check',
            self::DECLINED => 'x',
            self::MAYBE => 'question-mark-circle',
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
