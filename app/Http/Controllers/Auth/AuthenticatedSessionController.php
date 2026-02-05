<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected AuthTokenService $authTokenService,
    ) {}

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();

        // Revoke all existing tokens for this user (optional - single device login)
        // $user->tokens()->delete();

        // Issue access + refresh tokens
        $tokens = $this->authTokenService->issueTokens($user, $request);

        return response()
            ->json([
                'message' => 'Connexion réussie.',
                'user' => $user,
                'token' => $tokens['access_token'],
            ])
            ->withCookie($tokens['refresh_cookie']);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        // Revoke associated refresh token (if any) and clear cookie
        $this->authTokenService->revokeCurrentRefreshToken($request);

        return response()
            ->json([
                'message' => 'Déconnexion réussie.',
            ]);
    }
}
