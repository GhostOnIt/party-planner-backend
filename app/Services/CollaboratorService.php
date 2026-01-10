<?php

namespace App\Services;

use App\Enums\CollaboratorRole;
use App\Jobs\SendCollaborationInvitationJob;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class CollaboratorService
{
    /**
     * Invite a user to collaborate on an event.
     */
    public function invite(Event $event, User $user, string $role, ?int $customRoleId = null, bool $sendNotification = true): Collaborator
    {
        $collaborator = $event->collaborators()->create([
            'user_id' => $user->id,
            'role' => $role,
            'custom_role_id' => $customRoleId,
            'invited_at' => now(),
        ]);

        if ($sendNotification) {
            SendCollaborationInvitationJob::dispatch($collaborator);
        }

        return $collaborator;
    }

    /**
     * Invite a user by email.
     */
    public function inviteByEmail(Event $event, string $email, string $role, ?int $customRoleId = null): ?Collaborator
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return null;
        }

        // Check if already a collaborator
        if ($this->isCollaborator($event, $user)) {
            return null;
        }

        // Check if it's the owner
        if ($event->user_id === $user->id) {
            return null;
        }

        return $this->invite($event, $user, $role, $customRoleId);
    }

    /**
     * Accept a collaboration invitation.
     */
    public function accept(Collaborator $collaborator): Collaborator
    {
        $collaborator->update(['accepted_at' => now()]);

        // Notify the event owner
        $this->notifyOwnerOfAcceptance($collaborator);

        return $collaborator->fresh();
    }

    /**
     * Decline a collaboration invitation.
     */
    public function decline(Collaborator $collaborator): bool
    {
        // Notify the event owner
        $this->notifyOwnerOfDecline($collaborator);

        return $collaborator->delete();
    }

    /**
     * Update collaborator role.
     */
    public function updateRole(Collaborator $collaborator, string $role, ?int $customRoleId = null): Collaborator
    {
        // Cannot change owner role
        if ($collaborator->role === 'owner') {
            return $collaborator;
        }

        $collaborator->update([
            'role' => $role,
            'custom_role_id' => $customRoleId,
        ]);

        // Notify collaborator of role change
        $this->notifyRoleChange($collaborator);

        return $collaborator->fresh('customRole');
    }

    /**
     * Remove a collaborator.
     */
    public function remove(Collaborator $collaborator): bool
    {
        // Cannot remove owner
        if ($collaborator->role === 'owner') {
            return false;
        }

        // Notify the collaborator they've been removed
        $this->notifyRemoval($collaborator);

        return $collaborator->delete();
    }

    /**
     * Leave an event as a collaborator.
     */
    public function leave(Event $event, User $user): bool
    {
        $collaborator = $event->collaborators()
            ->where('user_id', $user->id)
            ->first();

        if (!$collaborator || $collaborator->role === 'owner') {
            return false;
        }

        return $collaborator->delete();
    }

    /**
     * Check if a user is a collaborator.
     */
    public function isCollaborator(Event $event, User $user): bool
    {
        return $event->collaborators()->where('user_id', $user->id)->exists();
    }

    /**
     * Get collaborator for a user on an event.
     */
    public function getCollaborator(Event $event, User $user): ?Collaborator
    {
        return $event->collaborators()->where('user_id', $user->id)->first();
    }

    /**
     * Get all collaborators for an event.
     */
    public function getCollaborators(Event $event): Collection
    {
        return $event->collaborators()
            ->with('user')
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'editor' THEN 2 WHEN 'viewer' THEN 3 END")
            ->get();
    }

    /**
     * Get pending invitations for an event.
     */
    public function getPendingInvitations(Event $event): Collection
    {
        return $event->collaborators()
            ->with('user')
            ->whereNull('accepted_at')
            ->get();
    }

    /**
     * Get accepted collaborators for an event.
     */
    public function getAcceptedCollaborators(Event $event): Collection
    {
        return $event->collaborators()
            ->with('user')
            ->whereNotNull('accepted_at')
            ->get();
    }

    /**
     * Get pending invitations for a user.
     */
    public function getUserPendingInvitations(User $user): Collection
    {
        return Collaborator::where('user_id', $user->id)
            ->with('event.user')
            ->whereNull('accepted_at')
            ->orderBy('invited_at', 'desc')
            ->get();
    }

    /**
     * Get user's collaborations (events they collaborate on).
     */
    public function getUserCollaborations(User $user): Collection
    {
        return Collaborator::where('user_id', $user->id)
            ->with('event')
            ->whereNotNull('accepted_at')
            ->get();
    }

    /**
     * Check if event can add more collaborators.
     * Now only requires an active subscription (no numerical limits).
     */
    public function canAddCollaborator(Event $event): bool
    {
        // Check if user has an active subscription
        $subscription = $event->subscription;

        return $subscription && $subscription->isActive();
    }

    /**
     * Get collaborator statistics (unlimited collaborators now).
     */
    public function getStatistics(Event $event): array
    {
        $collaborators = $event->collaborators;

        return [
            'total' => $collaborators->count(),
            'pending' => $collaborators->whereNull('accepted_at')->count(),
            'accepted' => $collaborators->whereNotNull('accepted_at')->count(),
            'editors' => $collaborators->whereIn('role', ['owner', 'editor', 'coordinator'])->count(),
            'viewers' => $collaborators->whereIn('role', ['viewer', 'supervisor', 'reporter'])->count(),
            'custom_roles' => $collaborators->whereNotNull('custom_role_id')->count(),
        ];
    }

    /**
     * Transfer ownership to another collaborator.
     */
    public function transferOwnership(Event $event, User $currentOwner, User $newOwner): bool
    {
        // Verify current owner
        if ($event->user_id !== $currentOwner->id) {
            return false;
        }

        // Get new owner's collaborator record
        $collaborator = $event->collaborators()
            ->where('user_id', $newOwner->id)
            ->first();

        if (!$collaborator) {
            return false;
        }

        // Update event owner
        $event->update(['user_id' => $newOwner->id]);

        // Update collaborator roles
        $collaborator->update(['role' => 'owner']);

        // Demote old owner to editor
        $event->collaborators()->create([
            'user_id' => $currentOwner->id,
            'role' => 'editor',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        return true;
    }

    /**
     * Resend invitation to a collaborator.
     */
    public function resendInvitation(Collaborator $collaborator): void
    {
        $collaborator->update(['invited_at' => now()]);

        SendCollaborationInvitationJob::dispatch($collaborator);
    }

    /**
     * Notify owner when invitation is accepted.
     */
    protected function notifyOwnerOfAcceptance(Collaborator $collaborator): void
    {
        Notification::create([
            'user_id' => $collaborator->event->user_id,
            'event_id' => $collaborator->event_id,
            'type' => 'collaboration_invite',
            'title' => 'Invitation acceptée',
            'message' => "{$collaborator->user->name} a accepté votre invitation à collaborer sur \"{$collaborator->event->title}\".",
            'sent_via' => 'push',
        ]);
    }

    /**
     * Notify owner when invitation is declined.
     */
    protected function notifyOwnerOfDecline(Collaborator $collaborator): void
    {
        Notification::create([
            'user_id' => $collaborator->event->user_id,
            'event_id' => $collaborator->event_id,
            'type' => 'collaboration_invite',
            'title' => 'Invitation déclinée',
            'message' => "{$collaborator->user->name} a décliné votre invitation à collaborer sur \"{$collaborator->event->title}\".",
            'sent_via' => 'push',
        ]);
    }

    /**
     * Notify collaborator of role change.
     */
    protected function notifyRoleChange(Collaborator $collaborator): void
    {
        $roleLabel = CollaboratorRole::tryFrom($collaborator->role)?->label() ?? $collaborator->role;

        Notification::create([
            'user_id' => $collaborator->user_id,
            'event_id' => $collaborator->event_id,
            'type' => 'collaboration_invite',
            'title' => 'Rôle modifié',
            'message' => "Votre rôle sur l'événement \"{$collaborator->event->title}\" a été modifié en {$roleLabel}.",
            'sent_via' => 'push',
        ]);
    }

    /**
     * Notify collaborator of removal.
     */
    protected function notifyRemoval(Collaborator $collaborator): void
    {
        Notification::create([
            'user_id' => $collaborator->user_id,
            'event_id' => $collaborator->event_id,
            'type' => 'collaboration_invite',
            'title' => 'Collaboration terminée',
            'message' => "Vous avez été retiré de l'événement \"{$collaborator->event->title}\".",
            'sent_via' => 'push',
        ]);
    }
}
