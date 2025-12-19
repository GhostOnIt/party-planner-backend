<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * Display public invitation page.
     */
    public function show(string $token): View
    {
        $invitation = Invitation::where('token', $token)
            ->with(['event', 'guest'])
            ->firstOrFail();

        // Mark as opened if first time
        if (!$invitation->opened_at) {
            $invitation->update(['opened_at' => now()]);
        }

        return view('invitations.show', compact('invitation'));
    }

    /**
     * Handle RSVP response.
     */
    public function respond(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)
            ->with('guest')
            ->firstOrFail();

        $validated = $request->validate([
            'response' => 'required|in:accepted,declined,maybe',
            'message' => 'nullable|string|max:500',
        ]);

        $invitation->update(['responded_at' => now()]);

        $invitation->guest->update([
            'rsvp_status' => $validated['response'],
            'notes' => $validated['message']
                ? ($invitation->guest->notes ? $invitation->guest->notes . "\n" : '') . "RSVP: " . $validated['message']
                : $invitation->guest->notes,
        ]);

        return redirect()
            ->route('invitations.show', $token)
            ->with('success', 'Votre réponse a été enregistrée. Merci !');
    }
}
