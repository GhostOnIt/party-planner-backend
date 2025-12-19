<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\Otp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Send an OTP code.
     *
     * POST /auth/otp/send
     */
    public function send(SendOtpRequest $request): JsonResponse
    {
        $identifier = $request->identifier;
        $type = $request->type;
        $channel = $request->channel;

        // Rate limiting: max 5 OTPs per identifier per hour
        $key = 'otp_send:' . $identifier;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'identifier' => ["Trop de demandes. Réessayez dans {$seconds} secondes."],
            ]);
        }

        // Find user if exists (for login and password reset)
        $user = null;
        if ($type !== Otp::TYPE_REGISTRATION) {
            if ($channel === Otp::CHANNEL_EMAIL) {
                $user = User::where('email', $identifier)->first();
            } else {
                $user = User::where('phone', $identifier)->first();
            }

            if (!$user) {
                // Don't reveal if user exists or not
                return response()->json([
                    'message' => 'Si un compte existe avec cet identifiant, un code vous sera envoyé.',
                    'otp_id' => null,
                    'expires_in' => Otp::EXPIRATION_MINUTES * 60,
                ]);
            }
        }

        // For registration, check if email/phone is already taken
        if ($type === Otp::TYPE_REGISTRATION) {
            if ($channel === Otp::CHANNEL_EMAIL) {
                $existingUser = User::where('email', $identifier)->first();
            } else {
                $existingUser = User::where('phone', $identifier)->first();
            }

            if ($existingUser) {
                throw ValidationException::withMessages([
                    'identifier' => ['Un compte existe déjà avec cet identifiant.'],
                ]);
            }
        }

        // Generate and send OTP
        $otp = $this->otpService->generateAndSend(
            identifier: $identifier,
            type: $type,
            channel: $channel,
            userId: $user?->id
        );

        // Record rate limit hit
        RateLimiter::hit($key, 3600); // 1 hour

        return response()->json([
            'message' => $this->getSendSuccessMessage($channel),
            'otp_id' => $otp->id,
            'expires_in' => Otp::EXPIRATION_MINUTES * 60,
        ]);
    }

    /**
     * Verify an OTP code.
     *
     * POST /auth/otp/verify
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $identifier = $request->identifier;
        $code = $request->code;
        $type = $request->type;

        $result = $this->otpService->verify($identifier, $code, $type);

        if (!$result['success']) {
            throw ValidationException::withMessages([
                'code' => [$result['message']],
            ]);
        }

        // Handle different OTP types
        return match ($type) {
            Otp::TYPE_REGISTRATION => $this->handleRegistrationVerification($result),
            Otp::TYPE_LOGIN => $this->handleLoginVerification($result),
            Otp::TYPE_PASSWORD_RESET => $this->handlePasswordResetVerification($result),
            default => response()->json([
                'success' => true,
                'message' => 'Code vérifié avec succès.',
            ]),
        };
    }

    /**
     * Resend an OTP code.
     *
     * POST /auth/otp/resend
     */
    public function resend(Request $request): JsonResponse
    {
        $request->validate([
            'otp_id' => ['required', 'integer', 'exists:otps,id'],
        ]);

        $result = $this->otpService->resend($request->otp_id);

        if (!$result['success']) {
            throw ValidationException::withMessages([
                'otp_id' => [$result['message']],
            ]);
        }

        return response()->json([
            'message' => 'Un nouveau code a été envoyé.',
            'otp_id' => $result['otp']->id,
            'expires_in' => Otp::EXPIRATION_MINUTES * 60,
        ]);
    }

    /**
     * Handle registration OTP verification.
     */
    protected function handleRegistrationVerification(array $result): JsonResponse
    {
        $otp = $result['otp'];

        // Generate a temporary token for completing registration
        $token = bin2hex(random_bytes(32));

        // Store token in cache for 10 minutes
        cache()->put(
            'registration_verified:' . $otp->identifier,
            $token,
            now()->addMinutes(10)
        );

        return response()->json([
            'success' => true,
            'message' => 'Vérification réussie. Vous pouvez maintenant compléter votre inscription.',
            'verified' => true,
            'verification_token' => $token,
        ]);
    }

    /**
     * Handle login OTP verification (2FA).
     */
    protected function handleLoginVerification(array $result): JsonResponse
    {
        $otp = $result['otp'];
        $user = User::find($otp->user_id);

        if (!$user) {
            throw ValidationException::withMessages([
                'code' => ['Utilisateur non trouvé.'],
            ]);
        }

        // Create auth token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Handle password reset OTP verification.
     */
    protected function handlePasswordResetVerification(array $result): JsonResponse
    {
        $otp = $result['otp'];

        // Generate a temporary token for password reset
        $token = bin2hex(random_bytes(32));

        // Store token in cache for 10 minutes
        cache()->put(
            'password_reset_verified:' . $otp->identifier,
            [
                'token' => $token,
                'user_id' => $otp->user_id,
            ],
            now()->addMinutes(10)
        );

        return response()->json([
            'success' => true,
            'message' => 'Code vérifié. Vous pouvez maintenant réinitialiser votre mot de passe.',
            'verified' => true,
            'reset_token' => $token,
        ]);
    }

    /**
     * Reset password after OTP verification.
     *
     * POST /auth/otp/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $identifier = strtolower($request->identifier);
        $cached = cache()->get('password_reset_verified:' . $identifier);

        if (!$cached || $cached['token'] !== $request->reset_token) {
            throw ValidationException::withMessages([
                'reset_token' => ['Token invalide ou expiré. Veuillez recommencer le processus.'],
            ]);
        }

        $user = User::find($cached['user_id']);

        if (!$user) {
            throw ValidationException::withMessages([
                'identifier' => ['Utilisateur non trouvé.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Clear the cache
        cache()->forget('password_reset_verified:' . $identifier);

        // Create auth token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Get success message based on channel.
     */
    protected function getSendSuccessMessage(string $channel): string
    {
        return match ($channel) {
            Otp::CHANNEL_EMAIL => 'Un code de vérification a été envoyé à votre adresse email.',
            Otp::CHANNEL_SMS => 'Un code de vérification a été envoyé par SMS.',
            Otp::CHANNEL_WHATSAPP => 'Un code de vérification a été envoyé via WhatsApp.',
            default => 'Un code de vérification a été envoyé.',
        };
    }
}
