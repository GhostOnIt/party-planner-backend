<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\BudgetItem;
use App\Models\User;
use App\Services\PermissionService;

class BudgetPolicy
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Perform pre-authorization checks.
     * Admins can do anything.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null; // Fall through to specific policy methods
    }

    /**
     * Determine whether the user can view any budget items for the event.
     */
    public function viewAny(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'budget.view');
    }

    /**
     * Determine whether the user can view the budget item.
     */
    public function view(User $user, BudgetItem $budgetItem): bool
    {
        return $this->permissionService->userCan($user, $budgetItem->event, 'budget.view');
    }

    /**
     * Determine whether the user can create budget items for the event.
     */
    public function create(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'budget.create');
    }

    /**
     * Determine whether the user can update the budget item.
     */
    public function update(User $user, BudgetItem $budgetItem): bool
    {
        return $this->permissionService->userCan($user, $budgetItem->event, 'budget.edit');
    }

    /**
     * Determine whether the user can delete the budget item.
     */
    public function delete(User $user, BudgetItem $budgetItem): bool
    {
        return $this->permissionService->userCan($user, $budgetItem->event, 'budget.delete');
    }

    /**
     * Determine whether the user can export the budget.
     */
    public function export(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'budget.export');
    }

    /**
     * Legacy method for backward compatibility.
     */
    public function manageBudget(User $user, Event $event): bool
    {
        return $this->permissionService->userCanInModule($user, $event, 'budget');
    }
}
