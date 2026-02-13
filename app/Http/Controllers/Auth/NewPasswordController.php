<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', new StrongPassword()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Votre mot de passe a été réinitialisé.',
            ]);
        }

        $message = match ($status) {
            Password::INVALID_USER => 'Aucun utilisateur trouvé avec cette adresse email.',
            Password::INVALID_TOKEN => 'Ce lien de réinitialisation n\'est plus valide. Demandez-en un nouveau.',
            Password::RESET_THROTTLED => 'Veuillez patienter avant de réessayer.',
            default => 'Impossible de réinitialiser le mot de passe.',
        };

        throw ValidationException::withMessages([
            'email' => [$message],
        ]);
    }
}
