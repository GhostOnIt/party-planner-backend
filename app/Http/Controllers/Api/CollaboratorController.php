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
        $remainingSlots = $this->collaboratorService->getRemainingSlots($event);

        return response()->json([
            'collaborators' => $collaborators,
            'stats' => $stats,
            'can_add_collaborator' => $canAddCollaborator,
            'remaining_slots' => $remainingSlots,
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
                'message' => 'Limite de collaborateurs atteinte.',
            ], 422);
        }

        $collaborator = $this->collaboratorService->inviteByEmail(
            $event,
            $request->validated('email'),
            $request->validated('role')
        );

        if (!$collaborator) {
            return response()->json([
                'message' => 'Impossible d\'inviter cet utilisateur.',
            ], 422);
        }

        return response()->json([
            'message' => 'Invitation envoyée.',
            'collaborator' => $collaborator->load('user'),
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

        $collaborator = $this->collaboratorService->updateRole($collaborator, $request->validated('role'));

        return response()->json([
            'message' => 'Rôle mis à jour.',
            'collaborator' => $collaborator->load('user'),
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
        $invitations = $this->collaboratorService->getUserPendingInvitations($request->user());

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
