<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    /**
     * Get public invitation details by guest token.
     * This endpoint is public and does not require authentication.
     */
    public function show(string $token): JsonResponse
    {
        // First try to find by guest invitation_token
        $guest = Guest::where('invitation_token', $token)
            ->with(['event' => function ($query) {
                $query->select('id', 'title', 'type', 'date', 'time', 'location', 'theme', 'description', 'user_id');
            }])
            ->first();

        if ($guest) {
            // Track that invitation was viewed
            if ($guest->invitation) {
                $guest->invitation->markAsOpened();
            }

            return response()->json([
                'guest' => [
                    'id' => $guest->id,
                    'name' => $guest->name,
                    'rsvp_status' => $guest->rsvp_status,
                ],
                'event' => [
                    'id' => $guest->event->id,
                    'title' => $guest->event->title,
                    'type' => $guest->event->type,
                    'date' => $guest->event->date,
                    'time' => $guest->event->time,
                    'location' => $guest->event->location,
                    'theme' => $guest->event->theme,
                    'description' => $guest->event->description,
                ],
                'already_responded' => $guest->rsvp_status !== 'pending',
            ]);
        }

        // Fallback: try legacy Invitation model token
        $invitation = Invitation::where('token', $token)
            ->with(['event' => function ($query) {
                $query->select('id', 'title', 'type', 'date', 'time', 'location', 'theme', 'description');
            }, 'guest' => function ($query) {
                $query->select('id', 'name', 'rsvp_status');
            }])
            ->first();

        if ($invitation) {
            if (!$invitation->opened_at) {
                $invitation->update(['opened_at' => now()]);
            }

            return response()->json([
                'guest' => [
                    'id' => $invitation->guest->id,
                    'name' => $invitation->guest->name,
                    'rsvp_status' => $invitation->guest->rsvp_status,
                ],
                'event' => [
                    'id' => $invitation->event->id,
                    'title' => $invitation->event->title,
                    'type' => $invitation->event->type,
                    'date' => $invitation->event->date,
                    'time' => $invitation->event->time,
                    'location' => $invitation->event->location,
                    'theme' => $invitation->event->theme,
                    'description' => $invitation->event->description,
                ],
                'already_responded' => $invitation->guest->rsvp_status !== 'pending',
            ]);
        }

        return response()->json([
            'message' => 'Invitation non trouvée ou lien invalide.',
        ], 404);
    }

    /**
     * Handle RSVP response from public invitation link.
     * This endpoint is public and does not require authentication.
     */
    public function respond(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'response' => 'required|in:accepted,declined,maybe',
            'message' => 'nullable|string|max:500',
            'guests_count' => 'nullable|integer|min:1|max:10',
        ]);

        // First try to find by guest invitation_token
        $guest = Guest::where('invitation_token', $token)->first();

        if ($guest) {
            $guest->update([
                'rsvp_status' => $validated['response'],
                'notes' => $validated['message']
                    ? ($guest->notes ? $guest->notes . "\n" : '') . "RSVP: " . $validated['message']
                    : $guest->notes,
            ]);

            // Update invitation if exists
            if ($guest->invitation) {
                $guest->invitation->update(['responded_at' => now()]);
            }

            return response()->json([
                'message' => $this->getResponseMessage($validated['response']),
                'rsvp_status' => $validated['response'],
                'guest_name' => $guest->name,
            ]);
        }

        // Fallback: try legacy Invitation model token
        $invitation = Invitation::where('token', $token)
            ->with('guest')
            ->first();

        if ($invitation) {
            $invitation->update(['responded_at' => now()]);

            $invitation->guest->update([
                'rsvp_status' => $validated['response'],
                'notes' => $validated['message']
                    ? ($invitation->guest->notes ? $invitation->guest->notes . "\n" : '') . "RSVP: " . $validated['message']
                    : $invitation->guest->notes,
            ]);

            return response()->json([
                'message' => $this->getResponseMessage($validated['response']),
                'rsvp_status' => $validated['response'],
                'guest_name' => $invitation->guest->name,
            ]);
        }

        return response()->json([
            'message' => 'Invitation non trouvée ou lien invalide.',
        ], 404);
    }

    /**
     * Get appropriate response message based on RSVP status.
     */
    protected function getResponseMessage(string $response): string
    {
        return match ($response) {
            'accepted' => 'Merci ! Votre présence est confirmée.',
            'declined' => 'Nous avons bien noté que vous ne pourrez pas venir.',
            'maybe' => 'Nous avons noté votre réponse. Merci de confirmer dès que possible.',
            default => 'Réponse enregistrée avec succès.',
        };
    }
}
