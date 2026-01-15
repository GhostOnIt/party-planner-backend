<?php

namespace App\Policies;

use App\Enums\CollaboratorRole;
use App\Models\Event;
use App\Models\User;
use App\Services\PermissionService;

class EventPolicy
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
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can always view their own events and events where they collaborate
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): bool
    {
        // Check if user has any permission on this event
        return $this->permissionService->userCanInModule($user, $event, 'events')
            || $this->permissionService->userCanInModule($user, $event, 'guests')
            || $this->permissionService->userCanInModule($user, $event, 'tasks')
            || $this->permissionService->userCanInModule($user, $event, 'budget')
            || $this->permissionService->userCanInModule($user, $event, 'photos')
            || $this->permissionService->userCanInModule($user, $event, 'collaborators');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'events.edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        // Only the owner can delete the event
        return $event->user_id === $user->id;
    }

    /**
     * Determine whether the user can manage collaborators.
     */
    public function collaborate(User $user, Event $event): bool
    {
        // Owner-only for collaborator management
        return $event->user_id === $user->id;
    }

    /**
     * Determine whether the user can invite collaborators.
     */
    public function inviteCollaborator(User $user, Event $event): bool
    {
        // Owner-only
        return $event->user_id === $user->id;
    }

    /**
     * Determine whether the user can remove a collaborator.
     */
    public function removeCollaborator(User $user, Event $event, User $collaboratorToRemove): bool
    {
        // Cannot remove the event owner
        if ($collaboratorToRemove->id === $event->user_id) {
            return false;
        }
        // Owner-only
        return $event->user_id === $user->id;
    }

    /**
     * Determine whether the user can manage guests.
     */
    public function manageGuests(User $user, Event $event): bool
    {
        return $this->permissionService->userCanInModule($user, $event, 'guests');
    }

    /**
     * Determine whether the user can manage tasks.
     */
    public function manageTasks(User $user, Event $event): bool
    {
        return $this->permissionService->userCanInModule($user, $event, 'tasks');
    }

    /**
     * Determine whether the user can manage budget.
     */
    public function manageBudget(User $user, Event $event): bool
    {
        return $this->permissionService->userCanInModule($user, $event, 'budget');
    }

    /**
     * Determine whether the user can manage photos.
     */
    public function managePhotos(User $user, Event $event): bool
    {
        return $this->permissionService->userCanInModule($user, $event, 'photos');
    }

    /**
     * Determine whether the user can send invitations.
     */
    public function sendInvitations(User $user, Event $event): bool
    {
        return $event->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can export event data.
     */
    public function export(User $user, Event $event): bool
    {
        return $event->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can duplicate the event.
     */
    public function duplicate(User $user, Event $event): bool
    {
        return $this->view($user, $event); // Same as view permission
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Event $event): bool
    {
        return $event->user_id === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $event->user_id === $user->id;
    }
}
