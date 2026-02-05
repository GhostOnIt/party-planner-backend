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
     * Create a custom role for a user (managed in settings; visible only to that user).
     */
    public function createRole(User $owner, array $data): CustomRole
    {
        $this->validateRoleCreationForUser($owner, $data);

        $role = CustomRole::create([
            'user_id' => $owner->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? 'gray',
            'is_system' => false,
            'created_by' => $owner->id,
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
     * Get system roles (global; no event needed).
     */
    public function getSystemRoles(): Collection
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
     * Get system roles for an event (delegates to getSystemRoles).
     */
    public function getSystemRolesForEvent(Event $event): Collection
    {
        return $this->getSystemRoles();
    }

    /**
     * Get custom roles assignable for an event (event owner's custom roles; they are unique per user).
     */
    public function getCustomRolesForEvent(Event $event): Collection
    {
        return CustomRole::forUser($event->user_id)
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
     * Validate role creation data (user-scoped; name unique per user).
     */
    private function validateRoleCreationForUser(User $owner, array $data): void
    {
        if (empty($data['name'])) {
            throw ValidationException::withMessages([
                'name' => 'Le nom du rôle est requis.'
            ]);
        }

        $existingRole = CustomRole::forUser($owner->id)
            ->where('name', $data['name'])
            ->first();

        if ($existingRole) {
            throw ValidationException::withMessages([
                'name' => 'Un rôle avec ce nom existe déjà.'
            ]);
        }

        $this->validatePermissions($data['permissions'] ?? []);
    }

    /**
     * Validate role update data (name unique per user).
     */
    private function validateRoleUpdate(CustomRole $role, array $data): void
    {
        if (isset($data['name']) && $data['name'] !== $role->name) {
            $existingRole = CustomRole::forUser($role->user_id)
                ->where('name', $data['name'])
                ->where('id', '!=', $role->id)
                ->first();

            if ($existingRole) {
                throw ValidationException::withMessages([
                    'name' => 'Un rôle avec ce nom existe déjà.'
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

}
