<?php

namespace App\Observers;

use App\Enums\BudgetCategory;
use App\Enums\CollaboratorRole;
use App\Enums\EventType;
use App\Models\User;
use App\Models\UserBudgetCategory;
use App\Models\UserCollaboratorRole;
use App\Models\UserEventType;
use App\Services\PermissionService;

class UserObserver
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $permissionService = app(PermissionService::class);
        
        // Create default event types
        $defaultEventTypes = [
            [
                'slug' => EventType::MARIAGE->value,
                'name' => EventType::MARIAGE->label(),
                'color' => EventType::MARIAGE->color(),
                'order' => 1,
            ],
            [
                'slug' => EventType::ANNIVERSAIRE->value,
                'name' => EventType::ANNIVERSAIRE->label(),
                'color' => EventType::ANNIVERSAIRE->color(),
                'order' => 2,
            ],
            [
                'slug' => EventType::BABY_SHOWER->value,
                'name' => EventType::BABY_SHOWER->label(),
                'color' => EventType::BABY_SHOWER->color(),
                'order' => 3,
            ],
            [
                'slug' => EventType::SOIREE->value,
                'name' => EventType::SOIREE->label(),
                'color' => EventType::SOIREE->color(),
                'order' => 4,
            ],
            [
                'slug' => EventType::BRUNCH->value,
                'name' => EventType::BRUNCH->label(),
                'color' => EventType::BRUNCH->color(),
                'order' => 5,
            ],
            [
                'slug' => EventType::AUTRE->value,
                'name' => EventType::AUTRE->label(),
                'color' => EventType::AUTRE->color(),
                'order' => 6,
            ],
        ];

        foreach ($defaultEventTypes as $type) {
            UserEventType::create([
                'user_id' => $user->id,
                'slug' => $type['slug'],
                'name' => $type['name'],
                'color' => $type['color'],
                'is_default' => true,
                'order' => $type['order'],
            ]);
        }

        // Create default collaborator roles (excluding photographer)
        $assignableRoles = array_filter(
            CollaboratorRole::assignableRoles(),
            fn($role) => $role !== CollaboratorRole::PHOTOGRAPHER
        );
        $order = 1;
        
        foreach ($assignableRoles as $role) {
            UserCollaboratorRole::create([
                'user_id' => $user->id,
                'slug' => $role->value,
                'name' => $role->label(),
                'description' => $role->description(),
                'permissions' => $permissionService->getSystemRolePermissions($role->value),
                'is_default' => true,
                'order' => $order++,
            ]);
        }

        // Create default budget categories
        $defaultBudgetCategories = [
            [
                'slug' => BudgetCategory::LOCATION->value,
                'name' => BudgetCategory::LOCATION->label(),
                'color' => BudgetCategory::LOCATION->color(),
                'order' => 1,
            ],
            [
                'slug' => BudgetCategory::CATERING->value,
                'name' => BudgetCategory::CATERING->label(),
                'color' => BudgetCategory::CATERING->color(),
                'order' => 2,
            ],
            [
                'slug' => BudgetCategory::DECORATION->value,
                'name' => BudgetCategory::DECORATION->label(),
                'color' => BudgetCategory::DECORATION->color(),
                'order' => 3,
            ],
            [
                'slug' => BudgetCategory::ENTERTAINMENT->value,
                'name' => BudgetCategory::ENTERTAINMENT->label(),
                'color' => BudgetCategory::ENTERTAINMENT->color(),
                'order' => 4,
            ],
            [
                'slug' => BudgetCategory::PHOTOGRAPHY->value,
                'name' => BudgetCategory::PHOTOGRAPHY->label(),
                'color' => BudgetCategory::PHOTOGRAPHY->color(),
                'order' => 5,
            ],
            [
                'slug' => BudgetCategory::TRANSPORTATION->value,
                'name' => BudgetCategory::TRANSPORTATION->label(),
                'color' => BudgetCategory::TRANSPORTATION->color(),
                'order' => 6,
            ],
            [
                'slug' => BudgetCategory::OTHER->value,
                'name' => BudgetCategory::OTHER->label(),
                'color' => BudgetCategory::OTHER->color(),
                'order' => 7,
            ],
        ];

        foreach ($defaultBudgetCategories as $category) {
            UserBudgetCategory::create([
                'user_id' => $user->id,
                'slug' => $category['slug'],
                'name' => $category['name'],
                'color' => $category['color'],
                'is_default' => true,
                'order' => $category['order'],
            ]);
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
