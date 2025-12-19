<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::USER => 'Utilisateur',
        };
    }

    /**
     * Get the color associated with the role.
     */
    public function color(): string
    {
        return match ($this) {
            self::ADMIN => 'red',
            self::USER => 'gray',
        };
    }

    /**
     * Get the icon associated with the role.
     */
    public function icon(): string
    {
        return match ($this) {
            self::ADMIN => 'shield-check',
            self::USER => 'user',
        };
    }

    /**
     * Check if role has admin privileges.
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options for forms.
     */
    public static function options(): array
    {
        return collect(self::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }
}
