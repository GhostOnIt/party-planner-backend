<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\OtpService;
use App\Services\SubscriptionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected OtpService $otpService,
    ) {}

    /**
     * Handle an incoming registration request.
     * Creates user, sends OTP by email, returns requires_otp (no tokens yet).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', new StrongPassword()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

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
        ], 201);
    }
}
