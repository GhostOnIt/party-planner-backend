<?php

namespace App\Http\Controllers;

use App\Http\Requests\Collaborator\StoreCollaboratorRequest;
use App\Http\Requests\Collaborator\UpdateCollaboratorRequest;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\User;
use App\Services\CollaboratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollaboratorController extends Controller
{
    public function __construct(
        protected CollaboratorService $collaboratorService
    ) {}

    /**
     * Display a listing of collaborators for an event.
     */
    public function index(Event $event): View
    {
        $this->authorize('view', $event);

        $collaborators = $this->collaboratorService->getCollaborators($event);
        $pendingInvitations = $this->collaboratorService->getPendingInvitations($event);
        $stats = $this->collaboratorService->getStatistics($event);
        $canAddCollaborator = $this->collaboratorService->canAddCollaborator($event);
        $remainingSlots = $this->collaboratorService->getRemainingSlots($event);
        $roles = \App\Enums\CollaboratorRole::options();

        return view('events.collaborators.index', compact(
            'event',
            'collaborators',
            'pendingInvitations',
            'stats',
            'canAddCollaborator',
            'remainingSlots',
            'roles'
        ));
    }

    /**
     * Invite a collaborator to the event.
     */
    public function store(StoreCollaboratorRequest $request, Event $event): RedirectResponse
    {
        if (!$this->collaboratorService->canAddCollaborator($event)) {
            return redirect()
                ->route('events.collaborators.index', $event)
                ->with('warning', 'Vous avez atteint la limite de collaborateurs. Passez à un forfait supérieur.');
        }

        $collaborator = $this->collaboratorService->inviteByEmail(
            $event,
            $request->validated('email'),
            $request->validated('role')
        );

        if (!$collaborator) {
            return redirect()
                ->route('events.collaborators.index', $event)
                ->with('error', 'Impossible d\'inviter cet utilisateur. Vérifiez qu\'il existe et n\'est pas déjà collaborateur.');
        }

        return redirect()
            ->route('events.collaborators.index', $event)
            ->with('success', 'Invitation envoyée avec succès.');
    }

    /**
     * Update the role of a collaborator.
     */
    public function update(UpdateCollaboratorRequest $request, Event $event, User $user): RedirectResponse
    {
        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return redirect()
                ->route('events.collaborators.index', $event)
                ->with('error', 'Collaborateur non trouvé.');
        }

        if ($collaborator->role === 'owner') {
            return redirect()
                ->route('events.collaborators.index', $event)
                ->with('error', 'Impossible de modifier le rôle du propriétaire.');
        }

        $this->collaboratorService->updateRole($collaborator, $request->validated('role'));

        return redirect()
            ->route('events.collaborators.index', $event)
            ->with('success', 'Rôle mis à jour avec succès.');
    }

    /**
     * Remove a collaborator from the event.
     */
    public function destroy(Event $event, User $user): RedirectResponse
    {
        $this->authorize('removeCollaborator', $event);

        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return redirect()
                ->route('events.collaborators.index', $event)
                ->with('error', 'Collaborateur non trouvé.');
        }

        if (!$this->collaboratorService->remove($collaborator)) {
            return redirect()
                ->route('events.collaborators.index', $event)
                ->with('error', 'Impossible de retirer ce collaborateur.');
        }

        return redirect()
            ->route('events.collaborators.index', $event)
            ->with('success', 'Collaborateur retiré avec succès.');
    }

    /**
     * Accept a collaboration invitation.
     */
    public function accept(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Invitation non trouvée.');
        }

        if ($collaborator->isAccepted()) {
            return redirect()
                ->route('events.show', $event)
                ->with('info', 'Vous avez déjà accepté cette invitation.');
        }

        $this->collaboratorService->accept($collaborator);

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Invitation acceptée ! Vous pouvez maintenant collaborer sur cet événement.');
    }

    /**
     * Decline a collaboration invitation.
     */
    public function decline(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Invitation non trouvée.');
        }

        $this->collaboratorService->decline($collaborator);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Invitation déclinée.');
    }

    /**
     * Leave an event as a collaborator.
     */
    public function leave(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();

        if (!$this->collaboratorService->leave($event, $user)) {
            return redirect()
                ->route('events.show', $event)
                ->with('error', 'Impossible de quitter cet événement.');
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Vous avez quitté l\'événement.');
    }

    /**
     * Resend invitation to a collaborator.
     */
    public function resendInvitation(Event $event, User $user): RedirectResponse
    {
        $this->authorize('inviteCollaborator', $event);

        $collaborator = $this->collaboratorService->getCollaborator($event, $user);

        if (!$collaborator) {
            return redirect()
                ->route('events.collaborators.index', $event)
                ->with('error', 'Collaborateur non trouvé.');
        }

        if ($collaborator->isAccepted()) {
            return redirect()
                ->route('events.collaborators.index', $event)
                ->with('info', 'Cette invitation a déjà été acceptée.');
        }

        $this->collaboratorService->resendInvitation($collaborator);

        return redirect()
            ->route('events.collaborators.index', $event)
            ->with('success', 'Invitation renvoyée avec succès.');
    }

    /**
     * Display user's pending invitations.
     */
    public function pendingInvitations(Request $request): View
    {
        $invitations = $this->collaboratorService->getUserPendingInvitations($request->user());

        return view('collaborations.pending', compact('invitations'));
    }

    /**
     * Display user's collaborations.
     */
    public function myCollaborations(Request $request): View
    {
        $collaborations = $this->collaboratorService->getUserCollaborations($request->user());

        return view('collaborations.index', compact('collaborations'));
    }

    /**
     * Get collaborator statistics (JSON for AJAX).
     */
    public function statistics(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $stats = $this->collaboratorService->getStatistics($event);
        $canAddCollaborator = $this->collaboratorService->canAddCollaborator($event);
        $remainingSlots = $this->collaboratorService->getRemainingSlots($event);

        return response()->json([
            'stats' => $stats,
            'can_add_collaborator' => $canAddCollaborator,
            'remaining_slots' => $remainingSlots,
        ]);
    }
}
