<?php

namespace App\Enums;

enum PlanType: string
{
    case STARTER = 'starter';
    case PRO = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::STARTER => 'Starter',
            self::PRO => 'Pro',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::STARTER => 'Idéal pour les petits événements',
            self::PRO => 'Pour les événements professionnels',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::STARTER => 'blue',
            self::PRO => 'purple',
        };
    }

    public function basePrice(): int
    {
        return match ($this) {
            self::STARTER => 5000,
            self::PRO => 15000,
        };
    }

    public function includedGuests(): int
    {
        return match ($this) {
            self::STARTER => 50,
            self::PRO => 200,
        };
    }

    public function pricePerExtraGuest(): int
    {
        return match ($this) {
            self::STARTER => 50,
            self::PRO => 30,
        };
    }

    public function maxCollaborators(): int
    {
        return match ($this) {
            self::STARTER => 2,
            self::PRO => PHP_INT_MAX, // Unlimited
        };
    }

    /**
     * Duration of the subscription in months.
     */
    public function durationInMonths(): int
    {
        return match ($this) {
            self::STARTER => 4,
            self::PRO => 8,
        };
    }

    /**
     * Get human-readable duration label.
     */
    public function durationLabel(): string
    {
        return match ($this) {
            self::STARTER => '4 mois',
            self::PRO => '8 mois',
        };
    }

    public function features(): array
    {
        return match ($this) {
            self::STARTER => [
                'Jusqu\'à 50 invités inclus',
                'Gestion des tâches',
                'Suivi du budget',
                'Invitations digitales',
                '2 collaborateurs maximum',
            ],
            self::PRO => [
                'Jusqu\'à 200 invités inclus',
                'Tout le plan Starter',
                'Collaborateurs illimités',
                'Export PDF',
                'Support prioritaire',
                'Thèmes personnalisés',
            ],
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
