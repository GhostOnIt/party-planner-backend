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
            $event = Event::find($request->route('event'));
        }

        if (!$event) {
            return $next($request);
        }

        $subscription = $event->subscription;
        $currentGuestCount = $event->guests()->count();

        // Free tier limit (before subscription)
        $freeLimit = 10;

        if (!$subscription) {
            if ($currentGuestCount >= $freeLimit) {
                return $this->guestLimitResponse($request, $event, $freeLimit);
            }
            return $next($request);
        }

        // Get plan limits
        $planType = PlanType::tryFrom($subscription->plan_type);
        $maxGuests = $planType ? $planType->includedGuests() : $freeLimit;

        // For paid plans, allow exceeding included guests (will be charged extra)
        // Just warn if exceeding included amount
        if ($subscription->isActive()) {
            return $next($request);
        }

        // If subscription not active, apply free limit
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
