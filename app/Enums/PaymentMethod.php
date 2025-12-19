<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case MTN_MOBILE_MONEY = 'mtn_mobile_money';
    case AIRTEL_MONEY = 'airtel_money';

    public function label(): string
    {
        return match ($this) {
            self::MTN_MOBILE_MONEY => 'MTN Mobile Money',
            self::AIRTEL_MONEY => 'Airtel Money',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MTN_MOBILE_MONEY => 'mtn',
            self::AIRTEL_MONEY => 'airtel',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MTN_MOBILE_MONEY => 'yellow',
            self::AIRTEL_MONEY => 'red',
        };
    }

    public function phonePrefix(): string
    {
        return match ($this) {
            self::MTN_MOBILE_MONEY => '67',
            self::AIRTEL_MONEY => '69',
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
