<?php

namespace App\Services;

use App\Enums\CollaboratorRole;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionService
{
    /**
     * Check if a user has a specific permission for an event.
     */
    public function userCan(User $user, Event $event, string $permission): bool
    {
        // Owner always has all permissions
        if ($event->user_id === $user->id) {
            return true;
        }

        $collaborator = $this->getCollaborator($event, $user);

        if (!$collaborator || !$collaborator->isAccepted()) {
            return false;
        }

        // Check custom role permissions first
        if ($collaborator->hasCustomRole() && $collaborator->customRole) {
            return $collaborator->customRole->hasPermission($permission);
        }

        // Check system role permissions for all roles
        $roles = $collaborator->getRoleValues();
        foreach ($roles as $role) {
            if ($this->systemRoleCan($role, $permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a user has any permission in a module for an event.
     */
    public function userCanInModule(User $user, Event $event, string $module): bool
    {
        // Owner always has all permissions
        if ($event->user_id === $user->id) {
            return true;
        }

        $collaborator = $this->getCollaborator($event, $user);

        if (!$collaborator || !$collaborator->isAccepted()) {
            return false;
        }

        // Check custom role permissions first
        if ($collaborator->hasCustomRole() && $collaborator->customRole) {
            return $collaborator->customRole->hasAnyPermissionInModule($module);
        }

        // Check system role permissions for all roles
        $roles = $collaborator->getRoleValues();
        foreach ($roles as $role) {
            if ($this->systemRoleCanInModule($role, $module)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all permissions for a user on an event.
     */
    public function getUserPermissions(User $user, Event $event): array
    {
        // Owner has all permissions
        if ($event->user_id === $user->id) {
            return Permission::pluck('name')->toArray();
        }

        $collaborator = $this->getCollaborator($event, $user);

        if (!$collaborator || !$collaborator->isAccepted()) {
            return [];
        }

        // Get custom role permissions
        if ($collaborator->hasCustomRole() && $collaborator->customRole) {
            return $collaborator->customRole->getPermissionNames();
        }

        // Get system role permissions (merge permissions from all roles)
        $roles = $collaborator->getRoleValues();
        $allPermissions = [];

        foreach ($roles as $role) {
            $rolePermissions = $this->getSystemRolePermissions($role);
            $allPermissions = array_merge($allPermissions, $rolePermissions);
        }

        return array_values(array_unique($allPermissions));
    }

    /**
     * Check if a system role has a specific permission.
     */
    public function systemRoleCan(string $role, string $permission): bool
    {
        $permissions = $this->getSystemRolePermissions($role);
        return in_array($permission, $permissions);
    }

    /**
     * Check if a system role has any permission in a module.
     */
    public function systemRoleCanInModule(string $role, string $module): bool
    {
        $permissions = $this->getSystemRolePermissions($role);
        return collect($permissions)->contains(function ($permission) use ($module) {
            return str_starts_with($permission, $module . '.');
        });
    }

    /**
     * Get all permissions for a system role.
     */
    public function getSystemRolePermissions(string $role): array
    {
        return match($role) {
            'owner' => $this->getAllPermissions(),
            'coordinator' => $this->getCoordinatorPermissions(),
            'guest_manager' => $this->getGuestManagerPermissions(),
            'planner' => $this->getPlannerPermissions(),
            'accountant' => $this->getAccountantPermissions(),
            'photographer' => $this->getPhotographerPermissions(),
            'supervisor' => $this->getSupervisorPermissions(),
            'reporter' => $this->getReporterPermissions(),
            // Legacy roles
            'editor' => $this->getCoordinatorPermissions(), // Migrate to coordinator
            'viewer' => $this->getSupervisorPermissions(), // Migrate to supervisor
            default => [],
        };
    }

    /**
     * Get all available permissions.
     */
    public function getAllPermissions(): array
    {
        return Permission::pluck('name')->toArray();
    }

    /**
     * Get coordinator permissions (almost everything).
     */
    private function getCoordinatorPermissions(): array
    {
        return [
            // Events
            'events.view', 'events.edit',
            // Guests
            'guests.view', 'guests.create', 'guests.edit', 'guests.delete',
            'guests.import', 'guests.export', 'guests.send_invitations', 'guests.checkin',
            // Tasks
            'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.delete', 'tasks.assign', 'tasks.complete',
            // Budget
            'budget.view', 'budget.create', 'budget.edit', 'budget.delete', 'budget.export',
            // Photos
            'photos.view', 'photos.upload', 'photos.delete', 'photos.set_featured',
            // Collaborators
            'collaborators.view', 'collaborators.invite', 'collaborators.edit_roles', 'collaborators.remove',
        ];
    }

    /**
     * Get guest manager permissions.
     */
    private function getGuestManagerPermissions(): array
    {
        return [
            'guests.view', 'guests.create', 'guests.edit', 'guests.delete',
            'guests.import', 'guests.export', 'guests.send_invitations', 'guests.checkin',
        ];
    }

    /**
     * Get planner permissions.
     */
    private function getPlannerPermissions(): array
    {
        return [
            'events.view', 'events.edit',
            'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.delete', 'tasks.assign', 'tasks.complete',
        ];
    }

    /**
     * Get accountant permissions.
     */
    private function getAccountantPermissions(): array
    {
        return [
            'budget.view', 'budget.create', 'budget.edit', 'budget.delete', 'budget.export',
        ];
    }

    /**
     * Get photographer permissions.
     */
    private function getPhotographerPermissions(): array
    {
        return [
            'photos.view', 'photos.upload', 'photos.delete', 'photos.set_featured',
        ];
    }

    /**
     * Get supervisor permissions (read-only).
     */
    private function getSupervisorPermissions(): array
    {
        return [
            'events.view', 'guests.view', 'tasks.view', 'budget.view', 'photos.view', 'collaborators.view',
        ];
    }

    /**
     * Get reporter permissions (read + export).
     */
    private function getReporterPermissions(): array
    {
        return [
            'events.view', 'guests.view', 'guests.export',
            'tasks.view', 'budget.view', 'budget.export',
            'photos.view', 'collaborators.view',
        ];
    }

    /**
     * Get collaborator for user and event.
     */
    private function getCollaborator(Event $event, User $user): ?Collaborator
    {
        return $event->collaborators()
            ->where('user_id', $user->id)
            ->with('customRole.permissions')
            ->first();
    }

    /**
     * Get permissions grouped by module for UI.
     */
    public function getPermissionsGroupedByModule(): array
    {
        return Permission::orderBy('module')
            ->orderBy('action')
            ->get()
            ->groupBy('module')
            ->map(function ($permissions, $module) {
                return [
                    'name' => $module,
                    'label' => $this->getModuleLabel($module),
                    'icon' => $this->getModuleIcon($module),
                    'permissions' => $permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'display_name' => $permission->display_name,
                            'description' => $permission->description,
                        ];
                    })->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get module label for display.
     */
    private function getModuleLabel(string $module): string
    {
        return match($module) {
            'events' => 'Événements',
            'guests' => 'Invités',
            'tasks' => 'Tâches',
            'budget' => 'Budget',
            'photos' => 'Photos',
            'collaborators' => 'Collaborateurs',
            default => ucfirst($module),
        };
    }

    /**
     * Get module icon for UI.
     */
    private function getModuleIcon(string $module): string
    {
        return match($module) {
            'events' => 'calendar',
            'guests' => 'users',
            'tasks' => 'checklist',
            'budget' => 'money',
            'photos' => 'camera',
            'collaborators' => 'user-plus',
            default => 'circle',
        };
    }
}
