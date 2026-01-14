<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use App\Services\PermissionService;

class GuestPolicy
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
     * Determine whether the user can view any guests for the event.
     */
    public function viewAny(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'guests.view');
    }

    /**
     * Determine whether the user can view the guest.
     */
    public function view(User $user, Guest $guest): bool
    {
        return $this->permissionService->userCan($user, $guest->event, 'guests.view');
    }

    /**
     * Determine whether the user can create guests for the event.
     */
    public function create(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'guests.create');
    }

    /**
     * Determine whether the user can update the guest.
     */
    public function update(User $user, Guest $guest): bool
    {
        return $this->permissionService->userCan($user, $guest->event, 'guests.edit');
    }

    /**
     * Determine whether the user can delete the guest.
     */
    public function delete(User $user, Guest $guest): bool
    {
        return $this->permissionService->userCan($user, $guest->event, 'guests.delete');
    }

    /**
     * Determine whether the user can send invitations.
     */
    public function sendInvitation(User $user, Guest $guest): bool
    {
        return $this->permissionService->userCan($user, $guest->event, 'guests.send_invitations');
    }

    /**
     * Determine whether the user can send reminders.
     */
    public function sendReminder(User $user, Guest $guest): bool
    {
        return $this->permissionService->userCan($user, $guest->event, 'guests.send_invitations');
    }

    /**
     * Determine whether the user can check in guests.
     */
    public function checkIn(User $user, Guest $guest): bool
    {
        return $this->permissionService->userCan($user, $guest->event, 'guests.checkin');
    }

    /**
     * Determine whether the user can undo check-in.
     */
    public function undoCheckIn(User $user, Guest $guest): bool
    {
        return $this->permissionService->userCan($user, $guest->event, 'guests.checkin');
    }

    /**
     * Determine whether the user can import guests.
     */
    public function import(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'guests.import');
    }

    /**
     * Determine whether the user can export guests.
     */
    public function export(User $user, Event $event): bool
    {
        return $this->permissionService->userCan($user, $event, 'guests.export');
    }

    /**
     * Determine whether the user can view invitation details.
     */
    public function viewInvitationDetails(User $user, Guest $guest): bool
    {
        return $this->permissionService->userCan($user, $guest->event, 'guests.view');
    }
}
