<?php

namespace App\Services;

use App\Enums\CollaboratorRole;
use App\Jobs\SendCollaborationInvitationGuestJob;
use App\Jobs\SendCollaborationInvitationJob;
use App\Models\CollaborationInvitation;
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
    public function invite(Event $event, User $user, string $role, array $customRoleIds = [], bool $sendNotification = true): Collaborator
    {
        return $this->inviteWithRoles($event, $user, [$role], $customRoleIds, $sendNotification);
    }

    /**
     * Invite a user to collaborate on an event with multiple roles.
     */
    public function inviteWithRoles(Event $event, User $user, array $roles, array $customRoleIds = [], bool $sendNotification = true): Collaborator
    {
        $collaborator = $event->collaborators()->create([
            'user_id' => $user->id,
            'role' => $roles[0] ?? null, // Keep primary role for backward compatibility
            // Keep legacy column populated with the "first" custom role for backward compatibility.
            'custom_role_id' => !empty($customRoleIds) ? $customRoleIds[0] : null,
            'invited_at' => now(),
            'invitation_token' => CollaborationInvitation::generateToken(),
        ]);

        // Add roles to pivot table using the CollaboratorRole model
        foreach ($roles as $role) {
            \App\Models\CollaboratorRole::create([
                'collaborator_id' => $collaborator->id,
                'role' => $role,
            ]);
        }

        // Sync custom roles (new multi-custom-role system)
        $collaborator->customRoles()->sync($customRoleIds);

        if ($sendNotification) {
            // Load relationships before sending email
            $collaborator->load(['collaboratorRoles', 'customRoles']);
            SendCollaborationInvitationJob::dispatch($collaborator);
        }

        return $collaborator;
    }

    /**
     * Invite a user by email.
     */
    public function inviteByEmail(Event $event, string $email, string $role, array $customRoleIds = []): ?Collaborator
    {
        return $this->inviteByEmailWithRoles($event, $email, [$role], $customRoleIds);
    }

    /**
     * Invite a user by email with multiple roles.
     * If user exists: create Collaborator and send existing-user email.
     * If user does not exist: create CollaborationInvitation and send guest email.
     */
    public function inviteByEmailWithRoles(Event $event, string $email, array $roles, array $customRoleIds = []): Collaborator|CollaborationInvitation|null
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            if ($this->isCollaborator($event, $user)) {
                return null;
            }
            if ($event->user_id === $user->id) {
                return null;
            }
            return $this->inviteWithRoles($event, $user, $roles, $customRoleIds);
        }

        // User does not exist: create pending invitation (guest)
        if ($this->hasPendingInvitation($event, $email)) {
            return null; // already invited this email
        }
        return $this->createPendingInvitation($event, $email, $roles, $customRoleIds);
    }

    /**
     * Check if there is already a pending collaboration invitation for this event + email.
     */
    public function hasPendingInvitation(Event $event, string $email): bool
    {
        return CollaborationInvitation::where('event_id', $event->id)
            ->where('email', $email)
            ->exists();
    }

    /**
     * Create a pending collaboration invitation (for email not yet registered) and send guest email.
     */
    public function createPendingInvitation(Event $event, string $email, array $roles, array $customRoleIds = []): CollaborationInvitation
    {
        $invitation = $event->collaborationInvitations()->create([
            'email' => $email,
            'roles' => $roles,
            'custom_role_ids' => $customRoleIds,
            'token' => CollaborationInvitation::generateToken(),
            'invited_at' => now(),
        ]);

        SendCollaborationInvitationGuestJob::dispatch($invitation);

        return $invitation;
    }

    /**
     * Claim pending collaboration invitations for a user (by email).
     * Called when listing invitations so that after login/register the user sees their pending invites.
     */
    public function claimPendingInvitationsForUser(User $user): void
    {
        $pending = CollaborationInvitation::where('email', $user->email)->get();

        foreach ($pending as $invitation) {
            $event = $invitation->event;
            if ($this->isCollaborator($event, $user)) {
                $invitation->delete();
                continue;
            }
            if ($event->user_id === $user->id) {
                $invitation->delete();
                continue;
            }

            $roles = $invitation->roles ?? [];
            $customRoleIds = $invitation->custom_role_ids ?? [];
            if (empty($roles) && !empty($customRoleIds)) {
                $roles = ['supervisor'];
            }
            if (empty($roles)) {
                $invitation->delete();
                continue;
            }

            $this->inviteWithRoles($event, $user, $roles, $customRoleIds, false);
            $invitation->delete();
        }
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
    public function updateRole(Collaborator $collaborator, string $role, array $customRoleIds = []): Collaborator
    {
        // Cannot change owner role
        if ($collaborator->role === 'owner') {
            return $collaborator;
        }

        $collaborator->update([
            'role' => $role,
            'custom_role_id' => !empty($customRoleIds) ? $customRoleIds[0] : null,
        ]);

        $collaborator->customRoles()->sync($customRoleIds);

        // Notify collaborator of role change
        $this->notifyRoleChange($collaborator);

        return $collaborator->fresh(['customRoles', 'collaboratorRoles']);
    }

    /**
     * Update collaborator roles.
     */
    public function updateRoles(Collaborator $collaborator, array $roles, array $customRoleIds = []): Collaborator
    {
        // Cannot change owner role
        if ($collaborator->hasRole('owner')) {
            return $collaborator;
        }

        // Update the primary role for backward compatibility
        $collaborator->update([
            'role' => $roles[0] ?? null,
            'custom_role_id' => !empty($customRoleIds) ? $customRoleIds[0] : null,
        ]);

        // Remove existing roles and add new ones
        $collaborator->collaboratorRoles()->delete();

        foreach ($roles as $role) {
            \App\Models\CollaboratorRole::create([
                'collaborator_id' => $collaborator->id,
                'role' => $role,
            ]);
        }

        // Sync custom roles (new multi-custom-role system)
        $collaborator->customRoles()->sync($customRoleIds);

        // Notify collaborator of role change
        $this->notifyRoleChange($collaborator);

        return $collaborator->fresh(['customRoles', 'collaboratorRoles']);
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
            ->with(['user', 'collaboratorRoles', 'customRoles'])
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'editor' THEN 2 WHEN 'viewer' THEN 3 WHEN 'coordinator' THEN 4 WHEN 'guest_manager' THEN 5 WHEN 'planner' THEN 6 WHEN 'accountant' THEN 7 WHEN 'supervisor' THEN 8 WHEN 'reporter' THEN 9 END")
            ->get();
    }

    /**
     * Get pending collaboration invitations (emails not yet registered) for an event.
     */
    public function getPendingCollaborationInvitations(Event $event): Collection
    {
        return $event->collaborationInvitations()
            ->orderBy('invited_at', 'desc')
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
            ->orderBy('created_at', 'desc') // Fallback sort by creation date
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
        // Current product rule in API/tests: collaborators are not paywalled.
        // (UI may still choose to restrict, but backend accepts the invite.)
        return true;
    }

    /**
     * Get remaining collaborator slots for an event.
     * Current product rule: unlimited collaborators (as long as subscription is active for inviting).
     */
    public function getRemainingSlots(Event $event): int
    {
        return PHP_INT_MAX;
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
            // Count collaborators having at least one custom role (new pivot system; legacy column also migrated).
            'custom_roles' => $event->collaborators()->whereHas('customRoles')->count(),
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
     * Get invitation by token (from collaboration_invitations or collaborators).
     *
     * @return array{invitation: Collaborator, message?: string}|array{error: 'wrong_account', message: string}|null null = 404
     */
    public function getInvitationByToken(string $token, User $user): array|null
    {
        // 1. Try collaboration_invitations first
        $collabInvitation = CollaborationInvitation::where('token', $token)->first();

        if ($collabInvitation) {
            if (strtolower($user->email) !== strtolower($collabInvitation->email)) {
                return [
                    'error' => 'wrong_account',
                    'message' => 'Cette invitation a été envoyée à une autre adresse email.',
                    'expected_email' => $collabInvitation->email,
                ];
            }
            // Claim: create Collaborator, delete CollaborationInvitation
            $roles = $collabInvitation->roles ?? [];
            $customRoleIds = $collabInvitation->custom_role_ids ?? [];
            if (empty($roles) && !empty($customRoleIds)) {
                $roles = ['supervisor'];
            }
            if (empty($roles)) {
                $roles = ['viewer'];
            }
            // Create collaborator with same token so /invite/{token} still works after claim
            $collaborator = $collabInvitation->event->collaborators()->create([
                'user_id' => $user->id,
                'role' => $roles[0] ?? null,
                'custom_role_id' => !empty($customRoleIds) ? $customRoleIds[0] : null,
                'invited_at' => now(),
                'invitation_token' => $collabInvitation->token,
            ]);
            foreach ($roles as $role) {
                \App\Models\CollaboratorRole::create([
                    'collaborator_id' => $collaborator->id,
                    'role' => $role,
                ]);
            }
            $collaborator->customRoles()->sync($customRoleIds);
            $collabInvitation->delete();

            return ['invitation' => $collaborator];
        }

        // 2. Try collaborators by invitation_token
        $collaborator = Collaborator::where('invitation_token', $token)->first();

        if ($collaborator) {
            if ($collaborator->user_id !== $user->id) {
                return [
                    'error' => 'wrong_account',
                    'message' => 'Cette invitation a été envoyée à un autre compte.',
                    'expected_email' => $collaborator->user->email ?? null,
                ];
            }
            return ['invitation' => $collaborator];
        }

        return null; // 404
    }

    /**
     * Resend invitation to a collaborator.
     */
    public function resendInvitation(Collaborator $collaborator): void
    {
        $updates = ['invited_at' => now()];
        if (!$collaborator->invitation_token) {
            $updates['invitation_token'] = CollaborationInvitation::generateToken();
        }
        $collaborator->update($updates);

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
