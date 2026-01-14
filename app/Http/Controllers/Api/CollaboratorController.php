<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collaborator\StoreCollaboratorRequest;
use App\Http\Requests\Collaborator\UpdateCollaboratorRequest;
use App\Models\Event;
use App\Models\User;
use App\Services\CollaboratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    public function __construct(
        protected CollaboratorService $collaboratorService
    ) {}

    /**
     * List collaborators for an event.
     */
    public function index(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $collaborators = $this->collaboratorService->getCollaborators($event);
        $stats = $this->collaboratorService->getStatistics($event);
        $canAddCollaborator = $this->collaboratorService->canAddCollaborator($event);
        $remainingSlots = PHP_INT_MAX; // Unlimited collaborators

        // Add roles to each collaborator for frontend compatibility
        $collaborators->transform(function ($collaborator) {
            $collaborator->roles = $collaborator->getRoleValues();
            return $collaborator;
        });

        return response()->json([
            'collaborators' => $collaborators,
            'stats' => $stats,
            'can_add_collaborator' => $canAddCollaborator,
            'remaining_slots' => PHP_INT_MAX, // Unlimited collaborators
        ]);
    }

    /**
     * Get collaborator statistics.
     */
    public function statistics(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json([
            'stats' => $this->collaboratorService->getStatistics($event),
            'can_add_collaborator' => $this->collaboratorService->canAddCollaborator($event),
            'remaining_slots' => $this->collaboratorService->getRemainingSlots($event),
        ]);
    }

    /**
     * Invite a collaborator.
     */
    public function store(StoreCollaboratorRequest $request, Event $event): JsonResponse
    {
        if (!$this->collaboratorService->canAddCollaborator($event)) {
            return response()->json([
                'message' => 'Un abonnement actif est requis pour inviter des collaborateurs.',
            ], 422);
        }

        $collaborator = $this->collaboratorService->inviteByEmailWithRoles(
            $event,
            $request->validated('email'),
            $request->validated('roles'),
            $request->validated('custom_role_id')
        );

        if (!$collaborator) {
            return response()->json([
                'message' => 'Impossible d\'inviter cet utilisateur.',
            ], 422);
        }

        // Add roles to collaborator for frontend compatibility
        $collaborator->roles = $collaborator->getRoleValues();
        $collaborator->load('user');

        return response()->json([
            'message' => 'Invitation envoyée.',
            'collaborator' => $collaborator,
        ], 201);
    }

    /**
     * Update collaborator role.
     */
    public function update(UpdateCollaboratorRequest $request, Event $event, User $user): JsonResponse
    {
        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return response()->json(['message' => 'Collaborateur non trouvé.'], 404);
        }

        if ($collaborator->role === 'owner') {
            return response()->json(['message' => 'Impossible de modifier le propriétaire.'], 422);
        }

        $collaborator = $this->collaboratorService->updateRoles($collaborator, $request->validated('roles'));

        // Add roles to collaborator for frontend compatibility
        $collaborator->roles = $collaborator->getRoleValues();
        $collaborator->load('user');

        return response()->json([
            'message' => 'Rôle mis à jour.',
            'collaborator' => $collaborator,
        ]);
    }

    /**
     * Remove a collaborator.
     */
    public function destroy(Event $event, User $user): JsonResponse
    {
        $this->authorize('removeCollaborator', [$event, $user]);

        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return response()->json(['message' => 'Collaborateur non trouvé.'], 404);
        }

        if (!$this->collaboratorService->remove($collaborator)) {
            return response()->json(['message' => 'Impossible de retirer ce collaborateur.'], 422);
        }

        return response()->json(['message' => 'Collaborateur retiré.']);
    }

    /**
     * Accept collaboration invitation.
     */
    public function accept(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return response()->json(['message' => 'Invitation non trouvée.'], 404);
        }

        if ($collaborator->isAccepted()) {
            return response()->json(['message' => 'Invitation déjà acceptée.'], 422);
        }

        $this->collaboratorService->accept($collaborator);

        return response()->json([
            'message' => 'Invitation acceptée.',
            'collaborator' => $collaborator->fresh()->load('user'),
        ]);
    }

    /**
     * Decline collaboration invitation.
     */
    public function decline(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();
        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return response()->json(['message' => 'Invitation non trouvée.'], 404);
        }

        $this->collaboratorService->decline($collaborator);

        return response()->json(['message' => 'Invitation déclinée.']);
    }

    /**
     * Leave an event.
     */
    public function leave(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if (!$this->collaboratorService->leave($event, $user)) {
            return response()->json(['message' => 'Impossible de quitter cet événement.'], 422);
        }

        return response()->json(['message' => 'Vous avez quitté l\'événement.']);
    }

    /**
     * Resend invitation.
     */
    public function resendInvitation(Event $event, User $user): JsonResponse
    {
        $this->authorize('inviteCollaborator', $event);

        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return response()->json(['message' => 'Collaborateur non trouvé.'], 404);
        }

        if ($collaborator->isAccepted()) {
            return response()->json(['message' => 'Invitation déjà acceptée.'], 422);
        }

        $this->collaboratorService->resendInvitation($collaborator);

        return response()->json(['message' => 'Invitation renvoyée.']);
    }

    /**
     * Get user's collaborations.
     */
    public function myCollaborations(Request $request): JsonResponse
    {
        $collaborations = $this->collaboratorService->getUserCollaborations($request->user());

        return response()->json(['collaborations' => $collaborations]);
    }

    /**
     * Get user's pending invitations.
     */
    public function pendingInvitations(Request $request): JsonResponse
    {
        $collaborators = $this->collaboratorService->getUserPendingInvitations($request->user());

        // Transform collaborators into invitation-compatible format
        $invitations = $collaborators->map(function ($collaborator) {
            // Ensure we have valid data
            $event = $collaborator->event;
            $inviter = $event ? $event->user : null;

            return [
                'id' => $collaborator->id,
                'event_id' => $collaborator->event_id,
                'event' => $event,
                'user_id' => $collaborator->user_id,
                'user' => $collaborator->user,
                'inviter_id' => $inviter ? $inviter->id : null,
                'inviter' => $inviter,
                'role' => $collaborator->role,
                'roles' => $collaborator->getRoleValues(),
                'status' => 'pending',
                'message' => null,
                'created_at' => $collaborator->invited_at ?? $collaborator->created_at ?? now(),
                'updated_at' => $collaborator->updated_at,
            ];
        });

        return response()->json(['invitations' => $invitations]);
    }

    /**
     * Accept invitation by collaborator ID.
     */
    public function acceptInvitationById(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $collaborator = $user->collaborations()->find($id);

        if (!$collaborator) {
            return response()->json(['message' => 'Invitation non trouvée.'], 404);
        }

        if ($collaborator->isAccepted()) {
            return response()->json(['message' => 'Invitation déjà acceptée.'], 422);
        }

        $this->collaboratorService->accept($collaborator);

        return response()->json([
            'message' => 'Invitation acceptée.',
            'collaborator' => $collaborator->fresh()->load(['user', 'event']),
        ]);
    }

    /**
     * Reject invitation by collaborator ID.
     */
    public function rejectInvitationById(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $collaborator = $user->collaborations()->find($id);

        if (!$collaborator) {
            return response()->json(['message' => 'Invitation non trouvée.'], 404);
        }

        if ($collaborator->isAccepted()) {
            return response()->json(['message' => 'Impossible de refuser une invitation déjà acceptée.'], 422);
        }

        $this->collaboratorService->decline($collaborator);

        return response()->json(['message' => 'Invitation refusée.']);
    }

    /**
     * Leave collaboration by event ID.
     */
    public function leaveByEventId(Request $request, int $eventId): JsonResponse
    {
        $user = $request->user();
        $event = Event::findOrFail($eventId);

        if (!$this->collaboratorService->leave($event, $user)) {
            return response()->json(['message' => 'Impossible de quitter cet événement.'], 422);
        }

        return response()->json(['message' => 'Vous avez quitté l\'événement.']);
    }
}
