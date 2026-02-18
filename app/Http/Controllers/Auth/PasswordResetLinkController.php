<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends Controller
{
    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status == Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Un lien de réinitialisation a été envoyé à votre adresse email.',
            ]);
        }

        $message = match ($status) {
            Password::INVALID_USER => 'Aucun utilisateur trouvé avec cette adresse email.',
            Password::RESET_THROTTLED => 'Veuillez patienter avant de réessayer.',
            default => 'Impossible d\'envoyer le lien de réinitialisation.',
        };

        throw ValidationException::withMessages([
            'email' => [$message],
        ]);
    }
}
