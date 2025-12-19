<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Features that require an active subscription.
     */
    protected array $premiumFeatures = [
        'budget.export',
        'photos.store',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        $event = $request->route('event');

        if (!$event instanceof Event) {
            $event = Event::find($request->route('event'));
        }

        if (!$event) {
            return $next($request);
        }

        $subscription = $event->subscription;

        // Check if subscription exists and is active
        if (!$subscription || !$subscription->isActive()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Un abonnement actif est requis pour cette fonctionnalité.',
                    'subscription_required' => true,
                ], 402);
            }

            return redirect()
                ->route('events.subscription.show', $event)
                ->with('warning', 'Un abonnement actif est requis pour accéder à cette fonctionnalité.');
        }

        // Check for specific feature access based on plan
        if ($feature && $this->requiresProPlan($feature) && !$subscription->isPro()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Cette fonctionnalité nécessite le plan Pro.',
                    'upgrade_required' => true,
                ], 402);
            }

            return redirect()
                ->route('events.subscription.show', $event)
                ->with('warning', 'Cette fonctionnalité nécessite le plan Pro.');
        }

        return $next($request);
    }

    /**
     * Check if a feature requires the Pro plan.
     */
    protected function requiresProPlan(string $feature): bool
    {
        $proFeatures = [
            'budget.export',
            'unlimited_collaborators',
        ];

        return in_array($feature, $proFeatures);
    }
}
