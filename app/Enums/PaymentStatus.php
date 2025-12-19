<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::COMPLETED => 'Complété',
            self::FAILED => 'Échoué',
            self::REFUNDED => 'Remboursé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
            self::REFUNDED => 'blue',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'clock',
            self::COMPLETED => 'check-circle',
            self::FAILED => 'x-circle',
            self::REFUNDED => 'arrow-uturn-left',
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
