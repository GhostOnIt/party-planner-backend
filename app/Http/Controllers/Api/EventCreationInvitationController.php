<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventCreationInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventCreationInvitationController extends Controller
{
    public function __construct(
        protected EventCreationInvitationService $service
    ) {}

    /**
     * Claim an event creation invitation by token.
     * Requires auth. User's email must match the invitation email.
     */
    public function claim(Request $request, string $token): JsonResponse
    {
        $result = $this->service->claimByToken($token, $request->user());

        if ($result === null) {
            return response()->json(['message' => 'Invitation introuvable ou expirée.'], 404);
        }

        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error'],
                'expected_email' => $result['expected_email'] ?? null,
            ], 422);
        }

        return response()->json([
            'message' => 'Événement récupéré avec succès.',
            'event_id' => $result['event_id'],
        ]);
    }
}
