<?php

namespace App\Services;

use App\Models\CustomRole;
use App\Models\Event;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomRoleService
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Create a custom role for an event.
     */
    public function createRole(Event $event, User $creator, array $data): CustomRole
    {
        $this->validateRoleCreation($event, $creator, $data);

        $role = CustomRole::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? 'gray',
            'is_system' => false,
            'created_by' => $creator->id,
        ]);

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $role->assignPermissions($data['permissions']);
        }

        return $role->fresh('permissions');
    }

    /**
     * Update a custom role.
     */
    public function updateRole(CustomRole $role, array $data): CustomRole
    {
        $this->validateRoleUpdate($role, $data);

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'description' => $data['description'] ?? $role->description,
            'color' => $data['color'] ?? $role->color,
        ]);

        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $role->assignPermissions($data['permissions']);
        }

        return $role->fresh('permissions');
    }

    /**
     * Delete a custom role.
     */
    public function deleteRole(CustomRole $role): bool
    {
        // Check if role is being used by collaborators
        if ($role->collaborators()->count() > 0) {
            throw ValidationException::withMessages([
                'role' => 'Ce rôle est utilisé par des collaborateurs et ne peut pas être supprimé.'
            ]);
        }

        return $role->delete();
    }

    /**
     * Get all roles for an event (system + custom).
     */
    public function getRolesForEvent(Event $event): Collection
    {
        $systemRoles = $this->getSystemRolesForEvent($event);
        $customRoles = $this->getCustomRolesForEvent($event);

        return $systemRoles->merge($customRoles);
    }

    /**
     * Get system roles for an event.
     */
    public function getSystemRolesForEvent(Event $event): Collection
    {
        $systemRoles = collect();

        foreach (\App\Enums\CollaboratorRole::systemRoles() as $roleEnum) {
            $systemRoles->push((object) [
                'id' => 'system_' . $roleEnum->value,
                'name' => $roleEnum->label(),
                'description' => $roleEnum->description(),
                'color' => $roleEnum->color(),
                'icon' => $roleEnum->icon(),
                'is_system' => true,
                'permissions' => $this->permissionService->getSystemRolePermissions($roleEnum->value),
                'created_at' => null,
                'updated_at' => null,
            ]);
        }

        return $systemRoles;
    }

    /**
     * Get custom roles for an event.
     */
    public function getCustomRolesForEvent(Event $event): Collection
    {
        return $event->customRoles()
            ->with('permissions')
            ->get()
            ->map(function ($role) {
                return (object) [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'color' => $role->color,
                    'icon' => $role->getIcon(),
                    'is_system' => false,
                    'permissions' => $role->getPermissionNames(),
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ];
            });
    }

    /**
     * Check if a user can create custom roles for an event.
     */
    public function canCreateCustomRoles(Event $event, User $user): bool
    {
        // Owner can always create custom roles
        if ($event->user_id === $user->id) {
            return true;
        }

        $collaborator = $event->collaborators()
            ->where('user_id', $user->id)
            ->first();

        if (!$collaborator || !$collaborator->isAccepted()) {
            return false;
        }

        // Check custom role permissions
        if ($collaborator->hasCustomRole() && $collaborator->customRole) {
            return $collaborator->customRole->hasPermission('collaborators.invite');
        }

        // Check system role permissions
        $roleEnum = \App\Enums\CollaboratorRole::tryFrom($collaborator->role);
        return $roleEnum && $roleEnum->canCreateCustomRoles();
    }

    /**
     * Validate role creation data.
     */
    private function validateRoleCreation(Event $event, User $creator, array $data): void
    {
        if (!$this->canCreateCustomRoles($event, $creator)) {
            throw ValidationException::withMessages([
                'user' => 'Vous n\'avez pas les permissions pour créer des rôles personnalisés.'
            ]);
        }

        if (empty($data['name'])) {
            throw ValidationException::withMessages([
                'name' => 'Le nom du rôle est requis.'
            ]);
        }

        // Check if role name already exists for this event
        $existingRole = $event->customRoles()
            ->where('name', $data['name'])
            ->first();

        if ($existingRole) {
            throw ValidationException::withMessages([
                'name' => 'Un rôle avec ce nom existe déjà pour cet événement.'
            ]);
        }

        $this->validatePermissions($data['permissions'] ?? []);
    }

    /**
     * Validate role update data.
     */
    private function validateRoleUpdate(CustomRole $role, array $data): void
    {
        if (isset($data['name']) && $data['name'] !== $role->name) {
            // Check if new name conflicts with existing roles
            $existingRole = $role->event->customRoles()
                ->where('name', $data['name'])
                ->where('id', '!=', $role->id)
                ->first();

            if ($existingRole) {
                throw ValidationException::withMessages([
                    'name' => 'Un rôle avec ce nom existe déjà pour cet événement.'
                ]);
            }
        }

        if (isset($data['permissions'])) {
            $this->validatePermissions($data['permissions']);
        }
    }

    /**
     * Validate permissions array.
     */
    private function validatePermissions(array $permissionIds): void
    {
        if (empty($permissionIds)) {
            throw ValidationException::withMessages([
                'permissions' => 'Au moins une permission doit être sélectionnée.'
            ]);
        }

        $existingPermissions = Permission::whereIn('id', $permissionIds)->count();

        if ($existingPermissions !== count($permissionIds)) {
            throw ValidationException::withMessages([
                'permissions' => 'Certaines permissions sélectionnées n\'existent pas.'
            ]);
        }
    }

    /**
     * Create system roles for a new event.
     */
    public function createSystemRolesForEvent(Event $event): void
    {
        foreach (\App\Enums\CollaboratorRole::systemRoles() as $roleEnum) {
            CustomRole::create([
                'event_id' => $event->id,
                'name' => $roleEnum->label(),
                'description' => $roleEnum->description(),
                'color' => $roleEnum->color(),
                'is_system' => true,
                'created_by' => $event->user_id,
            ]);
        }
    }
}
