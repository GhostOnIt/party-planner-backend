<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Models\Event;
use App\Models\Plan;
use App\Services\EntitlementService;
use App\Services\QuotaService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected QuotaService $quotaService,
        protected EntitlementService $entitlementService
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

    /**
     * Get current user's account-level subscription.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $this->subscriptionService->getUserActiveSubscription($user);
        $quota = $this->quotaService->getCreationsQuota($user);

        if (!$subscription) {
            return response()->json([
                'subscription' => null,
                'quota' => $quota,
                'has_subscription' => false,
            ]);
        }

        return response()->json([
            'subscription' => $subscription->load('plan'),
            'quota' => $quota,
            'has_subscription' => true,
        ]);
    }

    /**
     * Get event owner's entitlements (for collaborators to check if features are available).
     */
    public function eventEntitlements(Event $event): JsonResponse
    {
        // Check if user has access to this event (owner or collaborator)
        $this->authorize('view', $event);

        // Load the event owner if not already loaded
        if (!$event->relationLoaded('user')) {
            $event->load('user');
        }

        // Get entitlements for collaborators (considers admin owner, event.features_enabled)
        $owner = $event->user;

        if (!$owner) {
            return response()->json([
                'message' => 'Propriétaire de l\'événement introuvable.',
            ], 404);
        }

        $entitlements = $this->entitlementService->getEventEntitlements($event);

        return response()->json($entitlements);
    }

    /**
     * Get user's quota information.
     */
    public function quota(Request $request): JsonResponse
    {
        $user = $request->user();
        $quota = $this->quotaService->getCreationsQuota($user);
        $warning = $this->quotaService->shouldWarnAboutQuota($user);

        return response()->json([
            'quota' => $quota,
            'warning' => $warning,
        ]);
    }

    /**
     * Get user's entitlements (limits and features from account-level subscription).
     */
    public function entitlements(Request $request): JsonResponse
    {
        $user = $request->user();
        $entitlements = $this->entitlementService->getEffectiveEntitlements($user);

        return response()->json($entitlements);
    }

    /**
     * Subscribe to a plan (account-level).
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($validated['plan_id']);

        // Account-level active rights (paid or trial and not expired)
        $activeAccountSubscription = $user->subscriptions()
            ->whereNull('event_id')
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                    ->orWhere('status', 'trial');
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with('plan')
            ->latest()
            ->first();

        // Business rule: cannot activate free/trial plan while an account plan is active.
        if ($plan->is_trial && $activeAccountSubscription) {
            return response()->json([
                'message' => 'Vous avez déjà un abonnement actif. L\'essai gratuit n\'est pas disponible.',
                'subscription' => $activeAccountSubscription,
            ], 422);
        }

        // Existing account-level subscription (latest paid/pending)
        $existingSubscription = $user->subscriptions()
            ->whereNull('event_id')
            ->whereIn('payment_status', ['pending', 'paid'])
            ->with('plan')
            ->latest()
            ->first();
        
        // If user has an active subscription to the same plan, check its status
        if ($existingSubscription && $existingSubscription->plan_id === $plan->id) {
            // If subscription is paid and active, return error
            if ($existingSubscription->isActive() && $existingSubscription->isPaid()) {
                return response()->json([
                    'message' => 'Vous êtes déjà abonné à ce plan.',
                    'subscription' => $existingSubscription->load('plan'),
                ], 400);
            }
            
            // If subscription exists but is pending payment, update it (keep history)
            if ($existingSubscription->isPending() || !$existingSubscription->isPaid()) {
                // Update the existing subscription instead of creating a new one
                $existingSubscription->update([
                    'plan_id' => $plan->id,
                    'plan_type' => $plan->slug,
                    'base_price' => $plan->price,
                    'guest_count' => $plan->getGuestsLimit(),
                    'total_price' => $plan->price,
                    'payment_status' => $plan->is_trial ? 'paid' : 'pending',
                    'status' => $plan->is_trial ? 'trial' : 'pending',
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($plan->duration_days),
                ]);
                
                return response()->json([
                    'message' => $plan->is_trial
                        ? 'Essai gratuit activé avec succès.'
                        : 'Abonnement mis à jour. Procédez au paiement.',
                    'subscription' => $existingSubscription->fresh()->load('plan'),
                    'requires_payment' => !$plan->is_trial,
                ], 200);
            }
            
            // If subscription is expired, create a new one to keep history
            if ($existingSubscription->isExpired()) {
                // Create new subscription - old one remains in history with expired status
                // Don't update, create new to preserve history
            }
        }

        // If user has an active subscription to a different plan
        if ($existingSubscription && $existingSubscription->plan_id !== $plan->id) {
            $existingPlan = $existingSubscription->plan;

            $currentIsActive = ($existingSubscription->payment_status === 'paid' || $existingSubscription->status === 'trial')
                && ($existingSubscription->expires_at === null || $existingSubscription->expires_at->isFuture());

            // Check if this is an upgrade (new plan is superior)
            // Compare by price first, then by sort_order (lower sort_order = better plan)
            $isUpgrade = false;
            if ($existingPlan) {
                if ($plan->price > $existingPlan->price) {
                    $isUpgrade = true;
                } elseif ($plan->price === $existingPlan->price) {
                    $isUpgrade = ($plan->sort_order ?? 999) < ($existingPlan->sort_order ?? 999);
                }
            }

            if ($currentIsActive && !$isUpgrade) {
                // Downgrade or same tier - not allowed for active subscriptions
                return response()->json([
                    'message' => 'Vous ne pouvez pas passer à un plan inférieur tant que votre abonnement actif est en cours. Veuillez attendre l\'expiration de votre abonnement actuel.',
                    'subscription' => $existingSubscription->load('plan'),
                ], 400);
            }

            // Cancel old account-level line before creating the new one.
            $existingSubscription->update(['status' => 'cancelled']);
        }

        // Create new subscription
        $subscription = $this->subscriptionService->createSubscriptionWithPlan($user, $plan);

        return response()->json([
            'message' => $plan->is_trial
                ? 'Essai gratuit activé avec succès.'
                : 'Abonnement créé. Procédez au paiement.',
            'subscription' => $subscription->load('plan'),
            'requires_payment' => !$plan->is_trial,
        ], 201);
    }
}
