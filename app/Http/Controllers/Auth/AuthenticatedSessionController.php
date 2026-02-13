<?php

namespace App\Http\Controllers\Auth;

use App\Models\Otp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected AuthTokenService $authTokenService,
        protected OtpService $otpService,
    ) {}

    /**
     * Handle an incoming authentication request.
     * Validates credentials, sends OTP by email, returns requires_otp (no tokens yet).
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();
        $email = strtolower($request->input('email'));

        $otp = $this->otpService->generateAndSend(
            identifier: $email,
            type: Otp::TYPE_LOGIN,
            channel: Otp::CHANNEL_EMAIL,
            userId: $user->id
        );

        return response()->json([
            'message' => 'Un code de vérification a été envoyé à votre adresse email.',
            'requires_otp' => true,
            'identifier' => $email,
            'otp_id' => $otp->id,
            'channel' => Otp::CHANNEL_EMAIL,
            'expires_in' => Otp::EXPIRATION_MINUTES * 60,
        ]);
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
