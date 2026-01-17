<?php

namespace App\Http\Middleware;

use App\Enums\PlanType;
use App\Models\Event;
use App\Services\EntitlementService;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGuestLimit
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
        // Only check on guest creation
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

        $currentGuestCount = $event->guests()->count();

        // Use "maximum généreux" approach: get effective limit using MAX between
        // stored event limit and current account subscription limit
        $effectiveLimit = $this->entitlementService->getEffectiveLimit(
            $event,
            $event->user,
            'guests.max_per_event'
        );

        // -1 means unlimited
        if ($effectiveLimit === -1) {
            return $next($request);
        }

        // Check if current count exceeds effective limit
        if ($currentGuestCount >= $effectiveLimit) {
            return $this->guestLimitResponse($request, $event, $effectiveLimit);
        }

        return $next($request);
    }

    /**
     * Return guest limit exceeded response.
     */
    protected function guestLimitResponse(Request $request, Event $event, int $limit): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Limite de $limit invités atteinte. Veuillez souscrire à un abonnement pour ajouter plus d'invités.",
                'guest_limit_reached' => true,
                'current_limit' => $limit,
            ], 402);
        }

        return redirect()
            ->route('events.subscription.show', $event)
            ->with('warning', "Vous avez atteint la limite de $limit invités. Souscrivez à un abonnement pour en ajouter plus.");
    }
}
