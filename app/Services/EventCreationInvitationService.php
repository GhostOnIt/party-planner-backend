<?php

namespace App\Services;

use App\Jobs\SendEventCreatedForYouPendingJob;
use App\Models\Event;
use App\Models\EventCreationInvitation;
use App\Models\User;

class EventCreationInvitationService
{
    /**
     * Create a pending event creation invitation for a non-registered email.
     * The event is already created with admin as owner; on claim we transfer ownership.
     */
    public function createPendingInvitation(Event $event, string $email, User $admin): EventCreationInvitation
    {
        $invitation = $event->eventCreationInvitations()->create([
            'email' => strtolower($email),
            'token' => EventCreationInvitation::generateToken(),
            'admin_id' => $admin->id,
            'expires_at' => now()->addDays(30),
        ]);

        SendEventCreatedForYouPendingJob::dispatch($invitation);

        return $invitation;
    }

    /**
     * Claim an event creation invitation by token.
     * Verifies the user's email matches, then transfers event ownership to the user.
     *
     * @return array{event_id: string}|array{error: string, message: string, expected_email?: string}|null
     */
    public function claimByToken(string $token, User $user): array|null
    {
        $invitation = EventCreationInvitation::where('token', $token)
            ->with('event')
            ->first();

        if (!$invitation) {
            return null;
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return [
                'error' => 'wrong_account',
                'message' => 'Cette invitation a été envoyée à une autre adresse email.',
                'expected_email' => $invitation->email,
            ];
        }

        $event = $invitation->event;
        $event->update(['user_id' => $user->id]);
        $invitation->delete();

        return ['event_id' => $event->id];
    }

    /**
     * Get invitation by token (for frontend to check before redirect).
     */
    public function getInvitationByToken(string $token): ?EventCreationInvitation
    {
        return EventCreationInvitation::where('token', $token)
            ->with(['event', 'admin'])
            ->first();
    }
}
