<?php

namespace App\Http\Middleware;

use App\Enums\PlanType;
use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGuestLimit
{
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

        // Use max_guests_allowed stored on the event (set at creation time)
        // This allows events created during an active subscription to keep their limits
        // even after the subscription expires.
        if ($event->max_guests_allowed !== null) {
            if ($currentGuestCount >= $event->max_guests_allowed) {
                return $this->guestLimitResponse($request, $event, $event->max_guests_allowed);
            }
            return $next($request);
        }

        // Fallback: check current subscription (for backward compatibility with old events)
        $subscription = $event->user->getCurrentSubscription();
        $freeLimit = config('partyplanner.free_tier.max_guests', 10);

        if ($subscription && $subscription->isActive()) {
            $plan = $subscription->plan;
            if ($plan) {
                $maxGuests = $plan->getGuestsLimit();
                if ($currentGuestCount >= $maxGuests) {
                    return $this->guestLimitResponse($request, $event, $maxGuests);
                }
                return $next($request);
            }
        }

        // Free tier limit
        if ($currentGuestCount >= $freeLimit) {
            return $this->guestLimitResponse($request, $event, $freeLimit);
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
