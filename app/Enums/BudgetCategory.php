<?php

namespace App\Enums;

enum BudgetCategory: string
{
    case LOCATION = 'location';
    case CATERING = 'catering';
    case DECORATION = 'decoration';
    case ENTERTAINMENT = 'entertainment';
    case PHOTOGRAPHY = 'photography';
    case TRANSPORTATION = 'transportation';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LOCATION => 'Lieu',
            self::CATERING => 'Traiteur',
            self::DECORATION => 'Décoration',
            self::ENTERTAINMENT => 'Animation',
            self::PHOTOGRAPHY => 'Photographie',
            self::TRANSPORTATION => 'Transport',
            self::OTHER => 'Autre',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LOCATION => 'map-pin',
            self::CATERING => 'utensils',
            self::DECORATION => 'sparkles',
            self::ENTERTAINMENT => 'music',
            self::PHOTOGRAPHY => 'camera',
            self::TRANSPORTATION => 'car',
            self::OTHER => 'dots-horizontal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOCATION => 'blue',
            self::CATERING => 'orange',
            self::DECORATION => 'pink',
            self::ENTERTAINMENT => 'purple',
            self::PHOTOGRAPHY => 'cyan',
            self::TRANSPORTATION => 'green',
            self::OTHER => 'gray',
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
