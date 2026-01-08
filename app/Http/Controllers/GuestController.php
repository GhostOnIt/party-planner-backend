<?php

namespace App\Http\Controllers;

use App\Enums\RsvpStatus;
use App\Http\Requests\Guest\ImportGuestsRequest;
use App\Http\Requests\Guest\StoreGuestRequest;
use App\Http\Requests\Guest\UpdateGuestRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Services\GuestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GuestController extends Controller
{
    public function __construct(
        protected GuestService $guestService
    ) {}

    /**
     * Display a listing of guests for an event.
     */
    public function index(Request $request, Event $event): View
    {
        $this->authorize('view', $event);

        $query = $event->guests()->with('invitation');

        // Filter by RSVP status
        if ($request->filled('status')) {
            $query->where('rsvp_status', $request->status);
        }

        // Filter by invitation status
        if ($request->filled('invitation')) {
            if ($request->invitation === 'sent') {
                $query->whereNotNull('invitation_sent_at');
            } elseif ($request->invitation === 'not_sent') {
                $query->whereNull('invitation_sent_at');
            }
        }

        // Filter by check-in status
        if ($request->filled('checked_in')) {
            $query->where('checked_in', $request->checked_in === 'yes');
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $guests = $query
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $stats = $this->guestService->getStatistics($event);
        $rsvpStatuses = RsvpStatus::options();
        $canAddMore = $this->guestService->canAddGuest($event);
        $remainingSlots = $this->guestService->getRemainingSlots($event);

        return view('events.guests.index', compact(
            'event',
            'guests',
            'stats',
            'rsvpStatuses',
            'canAddMore',
            'remainingSlots'
        ));
    }

    /**
     * Show the form for creating a new guest.
     */
    public function create(Event $event): View|RedirectResponse
    {
        $this->authorize('manageGuests', $event);

        if (!$this->guestService->canAddGuest($event)) {
            return redirect()
                ->route('events.guests.index', $event)
                ->with('error', 'Vous avez atteint la limite d\'invités pour votre plan. Passez à un plan supérieur pour ajouter plus d\'invités.');
        }

        $rsvpStatuses = RsvpStatus::options();

        return view('events.guests.create', compact('event', 'rsvpStatuses'));
    }

    /**
     * Store a newly created guest.
     */
    public function store(StoreGuestRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('manageGuests', $event);

        if (!$this->guestService->canAddGuest($event)) {
            return redirect()
                ->route('events.guests.index', $event)
                ->with('error', 'Vous avez atteint la limite d\'invités pour votre plan.');
        }

        $this->guestService->create($event, $request->validated());

        return redirect()
            ->route('events.guests.index', $event)
            ->with('success', 'Invité ajouté avec succès.');
    }

    /**
     * Show the form for editing a guest.
     */
    public function edit(Event $event, Guest $guest): View
    {
        $this->authorize('manageGuests', $event);

        $rsvpStatuses = RsvpStatus::options();

        return view('events.guests.edit', compact('event', 'guest', 'rsvpStatuses'));
    }

    /**
     * Update the specified guest.
     */
    public function update(UpdateGuestRequest $request, Event $event, Guest $guest): RedirectResponse
    {
        $this->authorize('manageGuests', $event);

        $this->guestService->update($guest, $request->validated());

        return redirect()
            ->route('events.guests.index', $event)
            ->with('success', 'Invité mis à jour avec succès.');
    }

    /**
     * Remove the specified guest.
     */
    public function destroy(Event $event, Guest $guest): RedirectResponse
    {
        $this->authorize('manageGuests', $event);

        $this->guestService->delete($guest);

        return redirect()
            ->route('events.guests.index', $event)
            ->with('success', 'Invité supprimé avec succès.');
    }

    /**
     * Show the import form.
     */
    public function importForm(Event $event): View
    {
        $this->authorize('manageGuests', $event);

        $remainingSlots = $this->guestService->getRemainingSlots($event);

        return view('events.guests.import', compact('event', 'remainingSlots'));
    }

    /**
     * Import guests from CSV.
     */
    public function import(ImportGuestsRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('manageGuests', $event);

        $results = $this->guestService->importFromCsv(
            $event,
            $request->file('csv_file'),
            [
                'delimiter' => $request->input('delimiter', ','),
                'skip_duplicates' => $request->boolean('skip_duplicates', true),
            ]
        );

        $message = "{$results['imported']} invité(s) importé(s) avec succès.";

        if ($results['skipped'] > 0) {
            $message .= " {$results['skipped']} ligne(s) ignorée(s).";
        }

        if (!empty($results['errors'])) {
            $message .= " Erreurs : " . implode(', ', array_slice($results['errors'], 0, 3));
            if (count($results['errors']) > 3) {
                $message .= '...';
            }
        }

        return redirect()
            ->route('events.guests.index', $event)
            ->with($results['imported'] > 0 ? 'success' : 'warning', $message);
    }

    /**
     * Send invitation to a single guest.
     * If invitation was already sent, sends a reminder instead.
     */
    public function sendInvitation(Request $request, Event $event, Guest $guest): RedirectResponse
    {
        $this->authorize('sendInvitations', $event);

        try {
            $customMessage = $request->input('custom_message');
            $result = $this->guestService->sendInvitation($guest, $customMessage);

            $message = $result['type'] === 'reminder'
                ? "Rappel envoyé à {$guest->name}."
                : "Invitation envoyée à {$guest->name}.";

            return redirect()
                ->route('events.guests.index', $event)
                ->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('events.guests.index', $event)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Send invitations to all guests without sent invitations.
     */
    public function sendAllInvitations(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('sendInvitations', $event);

        $customMessage = $request->input('custom_message');

        $result = $this->guestService->sendBulkInvitations($event, null, $customMessage);

        if ($result['total'] === 0) {
            return redirect()
                ->route('events.guests.index', $event)
                ->with('info', 'Aucune invitation ou rappel à envoyer.');
        }

        $message = '';
        if ($result['invitations'] > 0 && $result['reminders'] > 0) {
            $message = "{$result['invitations']} invitation(s) et {$result['reminders']} rappel(s) en cours d'envoi.";
        } elseif ($result['invitations'] > 0) {
            $message = "{$result['invitations']} invitation(s) en cours d'envoi.";
        } elseif ($result['reminders'] > 0) {
            $message = "{$result['reminders']} rappel(s) en cours d'envoi.";
        }

        return redirect()
            ->route('events.guests.index', $event)
            ->with('success', $message);
    }

    /**
     * Send reminders to guests who haven't responded.
     */
    public function sendReminders(Event $event): RedirectResponse
    {
        $this->authorize('sendInvitations', $event);

        $count = $this->guestService->sendReminders($event);

        if ($count === 0) {
            return redirect()
                ->route('events.guests.index', $event)
                ->with('info', 'Aucun rappel à envoyer.');
        }

        return redirect()
            ->route('events.guests.index', $event)
            ->with('success', "{$count} rappel(s) en cours d'envoi.");
    }

    /**
     * Check-in a guest.
     */
    public function checkIn(Event $event, Guest $guest): RedirectResponse
    {
        $this->authorize('manageGuests', $event);

        $this->guestService->checkIn($guest);

        return redirect()
            ->back()
            ->with('success', "{$guest->name} a été enregistré(e).");
    }

    /**
     * Undo check-in for a guest.
     */
    public function undoCheckIn(Event $event, Guest $guest): RedirectResponse
    {
        $this->authorize('manageGuests', $event);

        $this->guestService->undoCheckIn($guest);

        return redirect()
            ->back()
            ->with('success', "Enregistrement de {$guest->name} annulé.");
    }

    /**
     * Update RSVP status.
     */
    public function updateRsvp(Request $request, Event $event, Guest $guest): RedirectResponse
    {
        $this->authorize('manageGuests', $event);

        $validated = $request->validate([
            'rsvp_status' => ['required', 'string', 'in:' . implode(',', RsvpStatus::values())],
        ]);

        $status = RsvpStatus::from($validated['rsvp_status']);
        $this->guestService->updateRsvpStatus($guest, $status);

        return redirect()
            ->back()
            ->with('success', 'Statut RSVP mis à jour.');
    }

    /**
     * Export guests to CSV.
     */
    public function export(Event $event): Response
    {
        $this->authorize('export', $event);

        $csv = $this->guestService->exportToCsv($event);

        $filename = 'invites_' . str_replace(' ', '_', $event->title) . '_' . now()->format('Y-m-d') . '.csv';

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Regenerate invitation token for a guest.
     */
    public function regenerateToken(Event $event, Guest $guest): RedirectResponse
    {
        $this->authorize('manageGuests', $event);

        $this->guestService->regenerateToken($guest);

        return redirect()
            ->back()
            ->with('success', 'Lien d\'invitation régénéré.');
    }
}
