<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\AuthTokenService;
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
        protected AuthTokenService $authTokenService,
    ) {}

    /**
     * Handle an incoming registration request.
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

        // Auto-create trial subscription for new user - DISABLED as per requirements
        // User must activate it manually
        // $subscription = $this->subscriptionService->createTrialSubscription($user);

        // Issue access + refresh tokens for immediate login
        $tokens = $this->authTokenService->issueTokens($user, $request);

        return response()
            ->json([
                'message' => 'Inscription réussie.',
                'user' => $user,
                'token' => $tokens['access_token'],
            ], 201)
            ->withCookie($tokens['refresh_cookie']);
    }
}
