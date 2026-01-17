<?php

namespace App\Http\Middleware;

use App\Enums\PlanType;
use App\Models\Event;
use App\Services\EntitlementService;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCollaboratorLimit
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected EntitlementService $entitlementService
    ) {}
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check on collaborator creation
        if (!in_array($request->method(), ['POST'])) {
            return $next($request);
        }

        $event = $request->route('event');

        if (!$event instanceof Event) {
            $eventId = $request->route('event');
            // Skip if event ID is null or undefined
            if ($eventId === null || $eventId === 'undefined' || $eventId === '') {
                return $next($request);
            }
            $event = Event::find($eventId);
        }

        if (!$event) {
            return $next($request);
        }

        $currentCollaboratorCount = $event->collaborators()->count();

        // Use "maximum généreux" approach: get effective limit using MAX between
        // stored event limit and current account subscription limit
        $effectiveLimit = $this->entitlementService->getEffectiveLimit(
            $event,
            $event->user,
            'collaborators.max_per_event'
        );

        // -1 means unlimited
        if ($effectiveLimit === -1) {
            return $next($request);
        }

        // Check if current count exceeds effective limit
        if ($currentCollaboratorCount >= $effectiveLimit) {
            return $this->collaboratorLimitResponse($request, $event, $effectiveLimit);
        }

        return $next($request);
    }

    /**
     * Return collaborator limit exceeded response.
     */
    protected function collaboratorLimitResponse(Request $request, Event $event, int $limit): Response
    {
        $message = $limit === PHP_INT_MAX
            ? 'Une erreur est survenue.'
            : "Limite de $limit collaborateur(s) atteinte. Passez au plan Pro pour des collaborateurs illimités.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'collaborator_limit_reached' => true,
                'current_limit' => $limit,
            ], 402);
        }

        return redirect()
            ->route('events.subscription.show', $event)
            ->with('warning', $message);
    }
}
