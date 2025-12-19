<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Models\Event;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Display subscription for an event.
     */
    public function show(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $subscription = $event->subscription;
        $guestCount = $event->guests()->count();
        $plans = $this->subscriptionService->getPlanComparison();
        $limits = $this->subscriptionService->checkPlanLimits($event);

        // Calculate prices for current guest count
        foreach ($plans as $key => &$plan) {
            $pricing = $this->subscriptionService->calculatePrice($key, $guestCount);
            $plan['calculated_price'] = $pricing;
        }

        return response()->json([
            'subscription' => $subscription,
            'guest_count' => $guestCount,
            'plans' => $plans,
            'limits' => $limits,
        ]);
    }

    /**
     * Create subscription.
     */
    public function store(StoreSubscriptionRequest $request, Event $event): JsonResponse
    {
        $guestCount = max($event->guests()->count(), $request->validated('guest_count', 0));

        $subscription = $this->subscriptionService->create(
            $event,
            $request->user(),
            $request->validated('plan_type'),
            $guestCount
        );

        return response()->json([
            'message' => 'Abonnement créé. Procédez au paiement.',
            'subscription' => $subscription,
        ], 201);
    }

    /**
     * Upgrade subscription.
     */
    public function upgrade(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'plan_type' => 'required|in:starter,pro',
            'guest_count' => 'nullable|integer|min:0',
        ]);

        $subscription = $event->subscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'Aucun abonnement actif pour cet événement.',
            ], 404);
        }

        $guestCount = $validated['guest_count'] ?? $subscription->guest_count;

        $subscription = $this->subscriptionService->upgrade(
            $subscription,
            $validated['plan_type'],
            $guestCount
        );

        return response()->json([
            'message' => 'Abonnement mis à jour.',
            'subscription' => $subscription,
            'requires_payment' => $subscription->isPending(),
        ]);
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $subscription = $event->subscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'Aucun abonnement à annuler.',
            ], 404);
        }

        $this->subscriptionService->cancel($subscription);

        return response()->json([
            'message' => 'Abonnement annulé.',
        ]);
    }

    /**
     * Renew subscription.
     */
    public function renew(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $subscription = $event->subscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'Aucun abonnement à renouveler.',
            ], 404);
        }

        $subscription = $this->subscriptionService->renew($subscription);

        return response()->json([
            'message' => 'Renouvellement initié. Procédez au paiement.',
            'subscription' => $subscription,
        ]);
    }

    /**
     * List user's subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $subscriptions = $this->subscriptionService->getUserSubscriptions($request->user());
        $stats = $this->subscriptionService->getStatistics();

        return response()->json([
            'subscriptions' => $subscriptions,
            'stats' => $stats,
        ]);
    }

    /**
     * Calculate price.
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
}
