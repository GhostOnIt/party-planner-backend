<?php

namespace App\Http\Middleware;

use App\Enums\PlanType;
use App\Models\Event;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCollaboratorLimit
{
    public function __construct(
        protected SubscriptionService $subscriptionService
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

        // Use max_collaborators_allowed stored on the event (set at creation time)
        // This allows events created during an active subscription to keep their limits
        // even after the subscription expires.
        if ($event->max_collaborators_allowed !== null) {
            // -1 represents unlimited
            if ($event->max_collaborators_allowed === -1) {
                return $next($request); // Unlimited
            }
            if ($currentCollaboratorCount >= $event->max_collaborators_allowed) {
                return $this->collaboratorLimitResponse($request, $event, $event->max_collaborators_allowed);
            }
            return $next($request);
        }

        // Fallback: check current subscription (for backward compatibility with old events)
        $subscription = $this->subscriptionService->getUserActiveSubscription($event->user);
        $freeLimit = config('partyplanner.free_tier.max_collaborators', 1);

        if ($subscription && $subscription->isActive()) {
            $plan = $subscription->plan;
            if ($plan) {
                $maxCollaborators = $plan->getCollaboratorsLimit();
                if ($maxCollaborators === -1) {
                    return $next($request); // Unlimited
                }
                if ($currentCollaboratorCount >= $maxCollaborators) {
                    return $this->collaboratorLimitResponse($request, $event, $maxCollaborators);
                }
                return $next($request);
            }
        }

        // Free tier limit
        if ($currentCollaboratorCount >= $freeLimit) {
            return $this->collaboratorLimitResponse($request, $event, $freeLimit);
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
