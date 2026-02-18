<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Services\PermissionService;

class TaskPolicy
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
     * Determine whether the user can view any tasks for the event.
     */
    public function viewAny(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'tasks.view');
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return $this->permissionService->userCan($user, $task->event, 'tasks.view');
    }

    /**
     * Determine whether the user can create tasks for the event.
     */
    public function create(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'tasks.create');
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        return $this->permissionService->userCan($user, $task->event, 'tasks.edit');
    }

    /**
     * Determine whether the user can update the status of the task.
     *
     * Rules:
     * - If the user can edit tasks => allowed
     * - Otherwise, allow the assigned user to change status (UX-friendly) as long as they can view tasks.
     */
    public function updateStatus(User $user, Task $task): bool
    {
        if ($this->permissionService->userCan($user, $task->event, 'tasks.edit')) {
            return true;
        }

        return !empty($task->assigned_to_user_id)
            && (string) $task->assigned_to_user_id === (string) $user->id
            && $this->permissionService->userCan($user, $task->event, 'tasks.view');
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        return $this->permissionService->userCan($user, $task->event, 'tasks.delete');
    }

    /**
     * Determine whether the user can assign tasks.
     */
    public function assign(User $user, Task $task): bool
    {
        return $this->permissionService->userCan($user, $task->event, 'tasks.assign');
    }

    /**
     * Determine whether the user can complete tasks.
     */
    public function complete(User $user, Task $task): bool
    {
        return $this->permissionService->userCan($user, $task->event, 'tasks.complete');
    }
}
