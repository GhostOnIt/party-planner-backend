<?php

namespace App\Policies;

use App\Enums\CollaboratorRole;
use App\Models\Event;
use App\Models\User;

class EventPolicy
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

        return null; // Fall through to specific policy methods
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): bool
    {
        return $event->canBeViewedBy($user);
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
        return $event->canBeEditedBy($user);
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
        // Only the owner can manage collaborators
        return $event->user_id === $user->id;
    }

    /**
     * Determine whether the user can invite collaborators.
     */
    public function inviteCollaborator(User $user, Event $event): bool
    {
        // Owner can always invite
        if ($event->user_id === $user->id) {
            return true;
        }

        // Check if user is a collaborator with owner role
        $collaborator = $event->collaborators()
            ->where('user_id', $user->id)
            ->first();

        return $collaborator && $collaborator->role === CollaboratorRole::OWNER->value;
    }

    /**
     * Determine whether the user can remove a collaborator.
     */
    public function removeCollaborator(User $user, Event $event, User $collaboratorToRemove): bool
    {
        // Owner can remove anyone except themselves
        if ($event->user_id === $user->id) {
            return $collaboratorToRemove->id !== $user->id;
        }

        // Collaborators with owner role can remove editors/viewers
        $userCollaborator = $event->collaborators()
            ->where('user_id', $user->id)
            ->first();

        if (!$userCollaborator || $userCollaborator->role !== CollaboratorRole::OWNER->value) {
            return false;
        }

        // Cannot remove the event owner
        if ($collaboratorToRemove->id === $event->user_id) {
            return false;
        }

        // Check if target is not an owner role
        $targetCollaborator = $event->collaborators()
            ->where('user_id', $collaboratorToRemove->id)
            ->first();

        return $targetCollaborator && $targetCollaborator->role !== CollaboratorRole::OWNER->value;
    }

    /**
     * Determine whether the user can manage guests.
     */
    public function manageGuests(User $user, Event $event): bool
    {
        return $event->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can manage tasks.
     */
    public function manageTasks(User $user, Event $event): bool
    {
        return $event->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can manage budget.
     */
    public function manageBudget(User $user, Event $event): bool
    {
        return $event->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can manage photos.
     */
    public function managePhotos(User $user, Event $event): bool
    {
        return $event->canBeEditedBy($user);
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
        return $event->canBeViewedBy($user);
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
