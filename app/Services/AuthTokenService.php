<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class AuthTokenService
{
    /**
     * Create a new access token and associated refresh token for the given user.
     *
     * @return array{access_token: string, refresh_cookie: \Symfony\Component\HttpFoundation\Cookie}
     */
    public function issueTokens(User $user, Request $request): array
    {
        // Short-lived access token (Sanctum personal access token)
        $accessToken = $user->createToken('auth-token')->plainTextToken;

        // Refresh token configuration
        $config = config('partyplanner.auth');
        $ttlDays = (int) ($config['refresh_token_ttl_days'] ?? 30);
        $expiresAt = now()->addDays($ttlDays);

        // Generate a secure random refresh token and store only the hash
        $rawToken = bin2hex(random_bytes(64));
        $hashed = hash('sha256', $rawToken);

        $user->refreshTokens()->create([
            'token_hash' => $hashed,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'expires_at' => $expiresAt,
            'last_used_at' => now(),
        ]);

        // Lifetime in minutes for the cookie
        $cookieMinutes = $ttlDays * 24 * 60;

        $refreshCookie = Cookie::make(
            'refresh_token',
            $rawToken,
            $cookieMinutes,
            '/',
            null,
            config('app.env') !== 'local',
            true,
            false,
            'lax'
        );

        return [
            'access_token' => $accessToken,
            'refresh_cookie' => $refreshCookie,
        ];
    }

    /**
     * Revoke the refresh token associated with the current request (if any).
     */
    public function revokeCurrentRefreshToken(Request $request): void
    {
        $rawToken = $request->cookie('refresh_token');

        if (!$rawToken) {
            return;
        }

        $hashed = hash('sha256', $rawToken);

        RefreshToken::where('token_hash', $hashed)
            ->where('revoked', false)
            ->update(['revoked' => true]);

        Cookie::queue(Cookie::forget('refresh_token', '/'));
    }
}

