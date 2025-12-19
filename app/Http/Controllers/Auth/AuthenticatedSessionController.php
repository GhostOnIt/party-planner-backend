<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();

        // Revoke all existing tokens for this user (optional - single device login)
        // $user->tokens()->delete();

        // Create new Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
