<?php

namespace App\Policies;

use App\Enums\CollaboratorRole;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\User;

class CollaboratorPolicy
{
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
        return $event->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can view the collaborator.
     */
    public function view(User $user, Collaborator $collaborator): bool
    {
        return $collaborator->event->canBeViewedBy($user);
    }

    /**
     * Determine whether the user can add collaborators to the event.
     */
    public function create(User $user, Event $event): bool
    {
        // Event owner can always add collaborators
        if ($event->user_id === $user->id) {
            return true;
        }

        // Check if user is a collaborator with owner role
        $userCollaborator = $event->collaborators()
            ->where('user_id', $user->id)
            ->first();

        return $userCollaborator && $userCollaborator->role === CollaboratorRole::OWNER->value;
    }

    /**
     * Determine whether the user can update the collaborator.
     */
    public function update(User $user, Collaborator $collaborator): bool
    {
        $event = $collaborator->event;

        // Event owner can update any collaborator
        if ($event->user_id === $user->id) {
            return true;
        }

        // Collaborator with owner role can update non-owner collaborators
        $userCollaborator = $event->collaborators()
            ->where('user_id', $user->id)
            ->first();

        if (!$userCollaborator || $userCollaborator->role !== CollaboratorRole::OWNER->value) {
            return false;
        }

        // Cannot update other owners or the event owner
        if ($collaborator->role === CollaboratorRole::OWNER->value) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the collaborator.
     */
    public function delete(User $user, Collaborator $collaborator): bool
    {
        $event = $collaborator->event;

        // Cannot remove the event owner from collaborators
        if ($collaborator->user_id === $event->user_id) {
            return false;
        }

        // Event owner can remove anyone except themselves
        if ($event->user_id === $user->id) {
            return true;
        }

        // Collaborator with owner role can remove editors/viewers
        $userCollaborator = $event->collaborators()
            ->where('user_id', $user->id)
            ->first();

        if (!$userCollaborator || $userCollaborator->role !== CollaboratorRole::OWNER->value) {
            return false;
        }

        // Cannot remove other owners
        return $collaborator->role !== CollaboratorRole::OWNER->value;
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

        $event = $collaborator->event;

        // Event owner can resend
        if ($event->user_id === $user->id) {
            return true;
        }

        // Owner-role collaborators can resend
        $userCollaborator = $event->collaborators()
            ->where('user_id', $user->id)
            ->first();

        return $userCollaborator && $userCollaborator->role === CollaboratorRole::OWNER->value;
    }
}
