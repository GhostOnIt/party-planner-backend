<?php

namespace Database\Seeders;

use App\Enums\CollaboratorRole;
use App\Models\User;
use App\Models\UserCollaboratorRole;
use App\Services\PermissionService;
use Illuminate\Database\Seeder;

class UserCollaboratorRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionService = app(PermissionService::class);
        
        // Get assignable roles (excluding owner)
        $assignableRoles = CollaboratorRole::assignableRoles();
        
        $defaultRoles = [];
        foreach ($assignableRoles as $role) {
            $defaultRoles[] = [
                'slug' => $role->value,
                'name' => $role->label(),
                'description' => $role->description(),
                'permissions' => $permissionService->getSystemRolePermissions($role->value),
                'order' => $this->getRoleOrder($role),
            ];
        }

        // Create default roles for all existing users
        User::chunk(100, function ($users) use ($defaultRoles) {
            foreach ($users as $user) {
                // Check if user already has roles
                if ($user->collaboratorRoles()->count() === 0) {
                    foreach ($defaultRoles as $role) {
                        UserCollaboratorRole::create([
                            'user_id' => $user->id,
                            'slug' => $role['slug'],
                            'name' => $role['name'],
                            'description' => $role['description'],
                            'permissions' => $role['permissions'],
                            'is_default' => true,
                            'order' => $role['order'],
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Get order for role.
     */
    private function getRoleOrder(CollaboratorRole $role): int
    {
        return match($role) {
            CollaboratorRole::COORDINATOR => 1,
            CollaboratorRole::GUEST_MANAGER => 2,
            CollaboratorRole::PLANNER => 3,
            CollaboratorRole::ACCOUNTANT => 4,
            CollaboratorRole::SUPERVISOR => 5,
            CollaboratorRole::REPORTER => 6,
            CollaboratorRole::EDITOR => 7, // Legacy
            CollaboratorRole::VIEWER => 8, // Legacy
            default => 10,
        };
    }
}
