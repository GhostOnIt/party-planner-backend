<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware global pour collecter les métriques Prometheus
        $middleware->append(\App\Http\Middleware\CollectPrometheusMetrics::class);
        
        $middleware->alias([
            'optional.sanctum' => \App\Http\Middleware\OptionalSanctum::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'event.access' => \App\Http\Middleware\EnsureEventAccess::class,
            'subscription.active' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'collaborator.role' => \App\Http\Middleware\CheckCollaboratorRole::class,
            'guest.limit' => \App\Http\Middleware\CheckGuestLimit::class,
            'collaborator.limit' => \App\Http\Middleware\CheckCollaboratorLimit::class,
            'invitation.track' => \App\Http\Middleware\TrackInvitationOpen::class,
            'check.quota' => \App\Http\Middleware\CheckQuota::class,
            'log.activity' => \App\Http\Middleware\LogApiActivity::class,
        ]);

        // Disable CSRF verification for webhook routes
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'payments/mtn/callback',
            'payments/airtel/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pour les requêtes API : ne jamais exposer exception, file, line, trace au client
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if (!$request->expectsJson()) {
                return null;
            }
            // Laisser Laravel gérer la validation (422 + errors)
            if ($e instanceof ValidationException) {
                return null;
            }
            // Authentification : 401 et non 500
            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => $e->getMessage()], 401);
            }

            // Modèle introuvable (route model binding) : 404 avec message clair pour l'API
            if ($e instanceof ModelNotFoundException) {
                $message = 'L\'événement demandé est introuvable ou vous n\'y avez pas accès.';
                if (!str_contains($e->getMessage(), 'Event')) {
                    $message = 'Ressource introuvable.';
                }
                return response()->json(['message' => $message], 404);
            }
            if ($e instanceof NotFoundHttpException && str_contains($e->getMessage(), 'No query results for model')) {
                return response()->json([
                    'message' => str_contains($e->getMessage(), 'Event')
                        ? 'L\'événement demandé est introuvable ou vous n\'y avez pas accès.'
                        : 'Ressource introuvable.',
                ], 404);
            }

            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            $message = $e instanceof HttpException ? $e->getMessage() : $e->getMessage();

            return response()->json(['message' => $message], $status);
        });
    })->create();
