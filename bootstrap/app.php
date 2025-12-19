<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'event.access' => \App\Http\Middleware\EnsureEventAccess::class,
            'subscription.active' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'collaborator.role' => \App\Http\Middleware\CheckCollaboratorRole::class,
            'guest.limit' => \App\Http\Middleware\CheckGuestLimit::class,
            'collaborator.limit' => \App\Http\Middleware\CheckCollaboratorLimit::class,
            'invitation.track' => \App\Http\Middleware\TrackInvitationOpen::class,
        ]);

        // Disable CSRF verification for webhook routes
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'payments/mtn/callback',
            'payments/airtel/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
