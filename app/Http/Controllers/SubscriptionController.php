<?php

namespace App\Http\Controllers;

use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Models\Event;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Display subscription plans for an event.
     */
    public function show(Event $event): View
    {
        $this->authorize('view', $event);

        $currentSubscription = $event->subscription;
        $guestCount = $event->guests()->count();
        $plans = $this->subscriptionService->getPlanComparison();
        $limits = $this->subscriptionService->checkPlanLimits($event);

        // Calculate prices for current guest count
        foreach ($plans as $key => &$plan) {
            $pricing = $this->subscriptionService->calculatePrice($key, $guestCount);
            $plan['calculated_price'] = $pricing;
        }

        return view('events.subscription.show', compact(
            'event',
            'currentSubscription',
            'guestCount',
            'plans',
            'limits'
        ));
    }

    /**
     * Subscribe to a plan.
     */
    public function subscribe(StoreSubscriptionRequest $request, Event $event): RedirectResponse
    {
        $guestCount = max($event->guests()->count(), $request->validated('guest_count', 0));

        $subscription = $this->subscriptionService->create(
            $event,
            $request->user(),
            $request->validated('plan_type'),
            $guestCount
        );

        return redirect()
            ->route('payments.initiate', ['subscription' => $subscription->id])
            ->with('success', 'Abonnement créé. Procédez au paiement.');
    }

    /**
     * Upgrade subscription.
     */
    public function upgrade(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'plan_type' => 'required|in:starter,pro',
            'guest_count' => 'nullable|integer|min:0',
        ]);

        $subscription = $event->subscription;

        if (!$subscription) {
            return redirect()
                ->route('events.subscription.show', $event)
                ->with('error', 'Aucun abonnement actif pour cet événement.');
        }

        $guestCount = $validated['guest_count'] ?? $subscription->guest_count;

        $subscription = $this->subscriptionService->upgrade(
            $subscription,
            $validated['plan_type'],
            $guestCount
        );

        if ($subscription->isPending()) {
            return redirect()
                ->route('payments.initiate', ['subscription' => $subscription->id])
                ->with('success', 'Mise à niveau effectuée. Procédez au paiement complémentaire.');
        }

        return redirect()
            ->route('events.subscription.show', $event)
            ->with('success', 'Abonnement mis à jour.');
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $subscription = $event->subscription;

        if (!$subscription) {
            return redirect()
                ->route('events.subscription.show', $event)
                ->with('error', 'Aucun abonnement à annuler.');
        }

        $this->subscriptionService->cancel($subscription);

        return redirect()
            ->route('events.subscription.show', $event)
            ->with('success', 'Abonnement annulé.');
    }

    /**
     * Renew subscription.
     */
    public function renew(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $subscription = $event->subscription;

        if (!$subscription) {
            return redirect()
                ->route('events.subscription.show', $event)
                ->with('error', 'Aucun abonnement à renouveler.');
        }

        $subscription = $this->subscriptionService->renew($subscription);

        return redirect()
            ->route('payments.initiate', ['subscription' => $subscription->id])
            ->with('success', 'Renouvellement initié. Procédez au paiement.');
    }

    /**
     * Display user's subscriptions.
     */
    public function index(Request $request): View
    {
        $subscriptions = $this->subscriptionService->getUserSubscriptions($request->user());
        $stats = $this->subscriptionService->getStatistics();

        return view('subscriptions.index', compact('subscriptions', 'stats'));
    }

    /**
     * Calculate price (AJAX).
     */
    public function calculatePrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_type' => 'required|in:starter,pro',
            'guest_count' => 'required|integer|min:0',
        ]);

        $pricing = $this->subscriptionService->calculatePrice(
            $validated['plan_type'],
            $validated['guest_count']
        );

        return response()->json($pricing);
    }

    /**
     * Check plan limits.
     */
    public function checkLimits(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $limits = $this->subscriptionService->checkPlanLimits($event);

        return response()->json($limits);
    }

    /**
     * Get plan comparison.
     */
    public function plans(): JsonResponse
    {
        return response()->json($this->subscriptionService->getPlanComparison());
    }
}
