<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Mail\PilotFeedbackMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    /**
     * Submit pilot-phase feedback (user accounts only; sent to configured inbox).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === UserRole::ADMIN) {
            return response()->json([
                'message' => 'Le feedback pilote est réservé aux comptes utilisateur.',
            ], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $recipient = config('feedback.mail_to');

        if (empty($recipient) || ! is_string($recipient)) {
            return response()->json([
                'message' => 'La réception des feedbacks n\'est pas configurée.',
            ], 503);
        }

        Mail::to($recipient)->send(new PilotFeedbackMail($user, $validated['message']));

        return response()->json([
            'message' => 'Merci, votre message a été envoyé.',
        ]);
    }
}
