<?php

namespace App\Enums;

enum CollaboratorRole: string
{
    // Legacy roles (to be migrated)
    case OWNER = 'owner';
    case EDITOR = 'editor'; // Will be migrated to COORDINATOR
    case VIEWER = 'viewer'; // Will be migrated to SUPERVISOR

    // New system roles
    case COORDINATOR = 'coordinator';
    case GUEST_MANAGER = 'guest_manager';
    case PLANNER = 'planner';
    case ACCOUNTANT = 'accountant';
    case PHOTOGRAPHER = 'photographer';
    case SUPERVISOR = 'supervisor';
    case REPORTER = 'reporter';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Propriétaire',
            self::COORDINATOR => 'Coordinateur',
            self::GUEST_MANAGER => 'Gestionnaire d\'Invités',
            self::PLANNER => 'Planificateur',
            self::ACCOUNTANT => 'Comptable',
            self::PHOTOGRAPHER => 'Photographe',
            self::SUPERVISOR => 'Superviseur',
            self::REPORTER => 'Rapporteur',
            // Legacy
            self::EDITOR => 'Éditeur',
            self::VIEWER => 'Lecteur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OWNER => 'Accès complet, peut supprimer l\'événement',
            self::COORDINATOR => 'Peut modifier tous les aspects de l\'événement',
            self::GUEST_MANAGER => 'Gestion complète des invités',
            self::PLANNER => 'Gestion des tâches et planning',
            self::ACCOUNTANT => 'Gestion du budget et finances',
            self::PHOTOGRAPHER => 'Gestion de la galerie photo',
            self::SUPERVISOR => 'Accès en lecture seule sur tout',
            self::REPORTER => 'Accès aux rapports et exports',
            // Legacy
            self::EDITOR => 'Peut modifier l\'événement',
            self::VIEWER => 'Peut uniquement consulter',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OWNER => 'yellow',
            self::COORDINATOR => 'purple',
            self::GUEST_MANAGER => 'blue',
            self::PLANNER => 'green',
            self::ACCOUNTANT => 'indigo',
            self::PHOTOGRAPHER => 'pink',
            self::SUPERVISOR => 'gray',
            self::REPORTER => 'orange',
            // Legacy
            self::EDITOR => 'blue',
            self::VIEWER => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OWNER => 'crown',
            self::COORDINATOR => 'target',
            self::GUEST_MANAGER => 'users',
            self::PLANNER => 'checklist',
            self::ACCOUNTANT => 'money',
            self::PHOTOGRAPHER => 'camera',
            self::SUPERVISOR => 'eye',
            self::REPORTER => 'file-text',
            // Legacy
            self::EDITOR => 'edit',
            self::VIEWER => 'eye',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [
            self::OWNER,
            self::COORDINATOR,
            self::GUEST_MANAGER,
            self::PLANNER,
            self::ACCOUNTANT,
            self::PHOTOGRAPHER,
            self::EDITOR, // Legacy
        ]);
    }

    public function canDelete(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Get all assignable roles (excluding owner).
     */
    public static function assignableRoles(): array
    {
        return [
            self::COORDINATOR,
            self::GUEST_MANAGER,
            self::PLANNER,
            self::ACCOUNTANT,
            self::PHOTOGRAPHER,
            self::SUPERVISOR,
            self::REPORTER,
            // Include legacy for migration
            self::EDITOR,
            self::VIEWER,
        ];
    }

    /**
     * Get system roles (predefined roles).
     */
    public static function systemRoles(): array
    {
        return [
            self::OWNER,
            self::COORDINATOR,
            self::GUEST_MANAGER,
            self::PLANNER,
            self::ACCOUNTANT,
            self::PHOTOGRAPHER,
            self::SUPERVISOR,
            self::REPORTER,
        ];
    }

    /**
     * Check if this role can manage collaborators.
     */
    public function canManageCollaborators(): bool
    {
        return in_array($this, [self::OWNER, self::COORDINATOR]);
    }

    /**
     * Check if this role can create custom roles.
     */
    public function canCreateCustomRoles(): bool
    {
        return in_array($this, [self::OWNER, self::COORDINATOR]);
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

    public static function assignableOptions(): array
    {
        return collect(self::assignableRoles())->mapWithKeys(fn($case) => [
            $case->value => $case->label()
        ])->toArray();
    }
}