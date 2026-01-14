<?php

namespace App\Policies;

use App\Enums\CollaboratorRole;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\User;
use App\Services\PermissionService;

class CollaboratorPolicy
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

        return null;
    }

    /**
     * Determine whether the user can view collaborators of an event.
     */
    public function viewAny(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'collaborators.view');
    }

    /**
     * Determine whether the user can view the collaborator.
     */
    public function view(User $user, Collaborator $collaborator): bool
    {
        return $this->permissionService->userCan($user, $collaborator->event, 'collaborators.view');
    }

    /**
     * Determine whether the user can add collaborators to the event.
     */
    public function create(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'collaborators.invite');
    }

    /**
     * Determine whether the user can update the collaborator.
     */
    public function update(User $user, Collaborator $collaborator): bool
    {
        return $this->permissionService->userCan($user, $collaborator->event, 'collaborators.edit_roles');
    }

    /**
     * Determine whether the user can delete the collaborator.
     */
    public function delete(User $user, Collaborator $collaborator): bool
    {
        // Cannot remove the event owner from collaborators
        if ($collaborator->user_id === $collaborator->event->user_id) {
            return false;
        }

        return $this->permissionService->userCan($user, $collaborator->event, 'collaborators.remove');
    }

    /**
     * Determine whether the user can accept the collaboration invitation.
     */
    public function accept(User $user, Collaborator $collaborator): bool
    {
        // Only the invited user can accept
        return $collaborator->user_id === $user->id && !$collaborator->isAccepted();
    }

    /**
     * Determine whether the user can decline the collaboration invitation.
     */
    public function decline(User $user, Collaborator $collaborator): bool
    {
        // Only the invited user can decline
        return $collaborator->user_id === $user->id && !$collaborator->isAccepted();
    }

    /**
     * Determine whether the user can leave the collaboration.
     */
    public function leave(User $user, Collaborator $collaborator): bool
    {
        // Only the collaborator themselves can leave
        if ($collaborator->user_id !== $user->id) {
            return false;
        }

        // Event owner cannot leave their own event
        if ($collaborator->event->user_id === $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can resend the invitation.
     */
    public function resendInvitation(User $user, Collaborator $collaborator): bool
    {
        // Cannot resend if already accepted
        if ($collaborator->isAccepted()) {
            return false;
        }

        return $this->permissionService->userCan($user, $collaborator->event, 'collaborators.invite');
    }
}
