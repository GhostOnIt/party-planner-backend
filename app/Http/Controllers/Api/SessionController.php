<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class SessionController extends Controller
{
    /**
     * List active sessions (refresh tokens) for the authenticated user.
     * Marks the session matching the current request's refresh token cookie as "current".
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenHash = $request->cookie('refresh_token')
            ? hash('sha256', $request->cookie('refresh_token'))
            : null;

        $sessions = $user->refreshTokens()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get()
            ->map(function (RefreshToken $token) use ($currentTokenHash) {
                return [
                    'id' => $token->id,
                    'device' => $this->parseUserAgent($token->user_agent),
                    'user_agent' => $token->user_agent,
                    'ip_address' => $token->ip_address,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at' => $token->created_at->toIso8601String(),
                    'expires_at' => $token->expires_at->toIso8601String(),
                    'is_current' => $currentTokenHash !== null && hash_equals($currentTokenHash, $token->token_hash),
                ];
            });

        return response()->json(['data' => $sessions->values()->all()]);
    }

    /**
     * Revoke a specific session. If it's the current session, also clear the refresh cookie.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $refreshToken = RefreshToken::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$refreshToken) {
            return response()->json(['message' => 'Session introuvable.'], 404);
        }

        $currentTokenHash = $request->cookie('refresh_token')
            ? hash('sha256', $request->cookie('refresh_token'))
            : null;
        $isCurrent = $currentTokenHash !== null && hash_equals($currentTokenHash, $refreshToken->token_hash);

        $refreshToken->update(['revoked' => true]);

        // Delete the access token(s) linked to this session so the revoked device is immediately logged out
        $user->tokens()->where('name', 'auth-token|' . $id)->delete();

        $response = response()->json([
            'message' => 'Session révoquée.',
            'current_session_revoked' => $isCurrent,
        ]);

        if ($isCurrent) {
            $response->withCookie(Cookie::forget('refresh_token', '/'));
        }

        return $response;
    }

    /**
     * Revoke all other sessions (keep only the current one).
     */
    public function revokeOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenHash = $request->cookie('refresh_token')
            ? hash('sha256', $request->cookie('refresh_token'))
            : null;

        if (!$currentTokenHash) {
            return response()->json([
                'message' => 'Impossible d\'identifier la session actuelle.',
            ], 400);
        }

        $toRevoke = $user->refreshTokens()
            ->where('revoked', false)
            ->where('token_hash', '!=', $currentTokenHash)
            ->get();

        $ids = $toRevoke->pluck('id')->all();
        foreach ($toRevoke as $token) {
            $token->update(['revoked' => true]);
        }

        // Delete access tokens linked to those sessions so revoked devices are immediately logged out
        if ($ids !== []) {
            $user->tokens()->whereIn('name', array_map(fn ($id) => 'auth-token|' . $id, $ids))->delete();
        }

        $count = count($ids);

        return response()->json([
            'message' => $count > 0
                ? "{$count} session(s) révoquée(s)."
                : 'Aucune autre session à révoquer.',
            'revoked_count' => $count,
        ]);
    }

    /**
     * Parse user agent string into a short device/browser label.
     */
    private function parseUserAgent(?string $ua): string
    {
        if (!$ua) {
            return 'Appareil inconnu';
        }

        $browser = 'Navigateur';
        $os = '';

        // Browsers (order matters for some edge cases)
        if (preg_match('/Edg\//i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/OPR\/|Opera/i', $ua)) {
            $browser = 'Opera';
        } elseif (preg_match('/Chrome\//i', $ua) && !preg_match('/Chromium/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari\//i', $ua) && !preg_match('/Chrome/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox\//i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/MSIE |Trident/i', $ua)) {
            $browser = 'Internet Explorer';
        }

        // OS
        if (preg_match('/Windows NT/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/iPhone|iPad/i', $ua)) {
            $os = preg_match('/iPad/i', $ua) ? 'iPadOS' : 'iOS';
        } elseif (preg_match('/Android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        }

        if ($os !== '') {
            return "{$browser} sur {$os}";
        }

        return $browser;
    }
}
