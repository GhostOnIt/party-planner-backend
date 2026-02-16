<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class RefreshTokenController extends Controller
{
    /**
     * Issue a new access token using a valid refresh token cookie.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $rawToken = $request->cookie('refresh_token');

        if (!$rawToken) {
            Log::warning('Refresh token missing from cookie', [
                'origin' => $request->header('Origin'),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Token de rafraîchissement manquant. Veuillez vous reconnecter.',
            ], 401);
        }

        $hashed = hash('sha256', $rawToken);

        /** @var RefreshToken|null $refreshToken */
        $refreshToken = RefreshToken::with('user')
            ->where('token_hash', $hashed)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$refreshToken || !$refreshToken->user) {
            Log::warning('Refresh token invalid or expired', [
                'token_found' => !!$refreshToken,
                'user_found' => $refreshToken?->user ? true : false,
                'ip' => $request->ip(),
            ]);

            Cookie::queue(Cookie::forget('refresh_token', '/'));

            return response()->json([
                'message' => 'Session expirée. Veuillez vous reconnecter.',
            ], 401);
        }

        $config = config('partyplanner.auth');
        $idleTimeoutMinutes = $config['refresh_token_idle_timeout_minutes'] ?? null;

        if ($idleTimeoutMinutes !== null && $refreshToken->last_used_at) {
            if ($refreshToken->last_used_at->diffInMinutes(now()) > (int) $idleTimeoutMinutes) {
                $refreshToken->forceFill(['revoked' => true])->save();
                Cookie::queue(Cookie::forget('refresh_token', '/'));

                return response()->json([
                    'message' => 'Session expirée après inactivité. Veuillez vous reconnecter.',
                ], 401);
            }
        }

        $user = $refreshToken->user;

        if (!$user->isActiveAccount()) {
            $refreshToken->forceFill(['revoked' => true])->save();
            Cookie::queue(Cookie::forget('refresh_token', '/'));

            return response()->json([
                'message' => 'Votre compte est inactif. Veuillez contacter le support.',
            ], 403);
        }

        // Mise à jour de la dernière utilisation
        $refreshToken->forceFill(['last_used_at' => now()])->save();

        // Création d’un nouveau token d’accès Sanctum
        $accessToken = $user->createToken('auth-token|' . $refreshToken->id)->plainTextToken;

        return response()->json([
            'message' => 'Token rafraîchi avec succès.',
            'user' => $user,
            'token' => $accessToken,
        ]);
    }
}

