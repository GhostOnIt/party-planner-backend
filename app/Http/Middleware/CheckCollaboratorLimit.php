<?php

namespace App\Http\Middleware;

use App\Enums\PlanType;
use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCollaboratorLimit
{
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
            $event = Event::find($request->route('event'));
        }

        if (!$event) {
            return $next($request);
        }

        $subscription = $event->subscription;
        $currentCollaboratorCount = $event->collaborators()->count();

        // Free tier: 1 collaborator max
        $freeLimit = 1;

        if (!$subscription || !$subscription->isActive()) {
            if ($currentCollaboratorCount >= $freeLimit) {
                return $this->collaboratorLimitResponse($request, $event, $freeLimit);
            }
            return $next($request);
        }

        // Get plan limits
        $planType = PlanType::tryFrom($subscription->plan_type);
        $maxCollaborators = $planType ? $planType->maxCollaborators() : $freeLimit;

        if ($currentCollaboratorCount >= $maxCollaborators) {
            return $this->collaboratorLimitResponse($request, $event, $maxCollaborators);
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
