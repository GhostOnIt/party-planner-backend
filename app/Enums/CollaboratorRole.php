<?php

namespace App\Enums;

enum CollaboratorRole: string
{
    case OWNER = 'owner';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Propriétaire',
            self::EDITOR => 'Éditeur',
            self::VIEWER => 'Lecteur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OWNER => 'Accès complet, peut supprimer l\'événement',
            self::EDITOR => 'Peut modifier l\'événement et gérer les invités',
            self::VIEWER => 'Peut uniquement consulter l\'événement',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OWNER => 'purple',
            self::EDITOR => 'blue',
            self::VIEWER => 'gray',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::OWNER, self::EDITOR]);
    }

    public function canDelete(): bool
    {
        return $this === self::OWNER;
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

    public static function assignableRoles(): array
    {
        return [self::EDITOR, self::VIEWER];
    }
}
