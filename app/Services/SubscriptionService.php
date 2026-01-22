<?php

namespace App\Services;

use App\Enums\PlanType;
use App\Models\Event;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

class SubscriptionService
{
    /**
     * Calculate price for a subscription.
     */
    public function calculatePrice(string $planType, int $guestCount): array
    {
        $plan = PlanType::tryFrom($planType);

        if (!$plan) {
            throw new \InvalidArgumentException("Invalid plan type: {$planType}");
        }

        $basePrice = $plan->basePrice();
        $includedGuests = $plan->includedGuests();
        $pricePerExtraGuest = $plan->pricePerExtraGuest();

        $extraGuests = max(0, $guestCount - $includedGuests);
        $extraGuestsCost = $extraGuests * $pricePerExtraGuest;
        $totalPrice = $basePrice + $extraGuestsCost;

        return [
            'plan_type' => $planType,
            'base_price' => $basePrice,
            'included_guests' => $includedGuests,
            'guest_count' => $guestCount,
            'extra_guests' => $extraGuests,
            'price_per_extra_guest' => $pricePerExtraGuest,
            'extra_guests_cost' => $extraGuestsCost,
            'total_price' => $totalPrice,
            'currency' => config('partyplanner.currency.code', 'XAF'),
        ];
    }

    /**
     * Create a subscription for an event.
     */
    public function create(Event $event, User $user, string $planType, int $guestCount): Subscription
    {
        $pricing = $this->calculatePrice($planType, $guestCount);
        $plan = PlanType::tryFrom($planType);
        $durationMonths = $plan ? $plan->durationInMonths() : 4;

        return Subscription::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'plan_type' => $planType,
            'base_price' => $pricing['base_price'],
            'guest_count' => $guestCount,
            'guest_price_per_unit' => $pricing['price_per_extra_guest'],
            'total_price' => $pricing['total_price'],
            'payment_status' => 'pending',
            'expires_at' => now()->addMonths($durationMonths),
        ]);
    }

    /**
     * Update a subscription.
     */
    public function update(Subscription $subscription, string $planType, int $guestCount): Subscription
    {
        $pricing = $this->calculatePrice($planType, $guestCount);

        $subscription->update([
            'plan_type' => $planType,
            'base_price' => $pricing['base_price'],
            'guest_count' => $guestCount,
            'guest_price_per_unit' => $pricing['price_per_extra_guest'],
            'total_price' => $pricing['total_price'],
        ]);

        return $subscription->fresh();
    }

    /**
     * Upgrade a subscription.
     */
    public function upgrade(Subscription $subscription, string $newPlanType, int $newGuestCount): Subscription
    {
        // Calculate price difference for proration (simplified)
        $oldPricing = $this->calculatePrice($subscription->plan_type, $subscription->guest_count);
        $newPricing = $this->calculatePrice($newPlanType, $newGuestCount);

        $priceDifference = $newPricing['total_price'] - $oldPricing['total_price'];

        // Only charge if upgrading to a more expensive plan
        if ($priceDifference > 0) {
            // Store upgrade info in metadata for payment
            $subscription->update([
                'plan_type' => $newPlanType,
                'base_price' => $newPricing['base_price'],
                'guest_count' => $newGuestCount,
                'guest_price_per_unit' => $newPricing['price_per_extra_guest'],
                'total_price' => $newPricing['total_price'],
                'payment_status' => 'pending', // Requires additional payment
            ]);
        } else {
            // Downgrade or same price - just update
            $subscription->update([
                'plan_type' => $newPlanType,
                'base_price' => $newPricing['base_price'],
                'guest_count' => $newGuestCount,
                'guest_price_per_unit' => $newPricing['price_per_extra_guest'],
                'total_price' => $newPricing['total_price'],
            ]);
        }

        return $subscription->fresh();
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'expires_at' => now(),
        ]);

        return $subscription->fresh();
    }

    /**
     * Renew a subscription.
     */
    public function renew(Subscription $subscription): Subscription
    {
        $plan = PlanType::tryFrom($subscription->plan_type);
        $durationMonths = $plan ? $plan->durationInMonths() : 4;

        $subscription->update([
            'expires_at' => now()->addMonths($durationMonths),
            'payment_status' => 'pending',
        ]);

        return $subscription->fresh();
    }

    /**
     * Check if user can use a specific feature.
     */
    public function canUseFeature(Subscription $subscription, string $feature): bool
    {
        $plan = PlanType::tryFrom($subscription->plan_type);

        if (!$plan || !$subscription->isActive()) {
            return false;
        }

        $features = $plan->features();

        // Simple feature check - could be expanded with feature flags
        return in_array($feature, $features);
    }

    /**
     * Get maximum guests allowed for a subscription.
     */
    public function getMaxGuests(Subscription $subscription): int
    {
        return $subscription->guest_count;
    }

    /**
     * Check if event can add more guests.
     * Uses "maximum généreux" approach: get effective limit using MAX between
     * stored event limit and current account subscription limit.
     */
    public function canAddGuests(Event $event, int $additionalGuests = 1): bool
    {
        $entitlementService = app(EntitlementService::class);
        
        // Get effective limit using MAX between stored and current subscription
        $effectiveLimit = $entitlementService->getEffectiveLimit(
            $event,
            $event->user,
            'guests.max_per_event'
        );

        // -1 means unlimited
        if ($effectiveLimit === -1) {
            return true;
        }

        $currentGuests = $event->guests()->count();
        return ($currentGuests + $additionalGuests) <= $effectiveLimit;
    }

    /**
     * Get remaining guest slots.
     * Uses "maximum généreux" approach: get effective limit using MAX between
     * stored event limit and current account subscription limit.
     */
    public function getRemainingGuestSlots(Event $event): int
    {
        $entitlementService = app(EntitlementService::class);
        
        // Get effective limit using MAX between stored and current subscription
        $effectiveLimit = $entitlementService->getEffectiveLimit(
            $event,
            $event->user,
            'guests.max_per_event'
        );

        // -1 means unlimited
        if ($effectiveLimit === -1) {
            return PHP_INT_MAX;
        }

        $currentGuests = $event->guests()->count();
        return max(0, $effectiveLimit - $currentGuests);
    }

    /**
     * Get plan comparison data.
     */
    public function getPlanComparison(): array
    {
        $plans = [];

        foreach (PlanType::cases() as $plan) {
            $plans[$plan->value] = [
                'name' => $plan->label(),
                'description' => $plan->description(),
                'base_price' => $plan->basePrice(),
                'included_guests' => $plan->includedGuests(),
                'price_per_extra_guest' => $plan->pricePerExtraGuest(),
                'max_collaborators' => $plan->maxCollaborators(),
                'features' => $plan->features(),
                'color' => $plan->color(),
                'duration_months' => $plan->durationInMonths(),
                'duration_label' => $plan->durationLabel(),
            ];
        }

        return $plans;
    }

    /**
     * Get user's subscriptions.
     * Excludes pending subscriptions (payment_status = 'pending').
     */
    public function getUserSubscriptions(User $user): Collection
    {
        return $user->subscriptions()
            ->where('payment_status', '!=', 'pending')
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active subscription for an event.
     */
    public function getActiveSubscription(Event $event): ?Subscription
    {
        return $event->subscription()
            ->where('payment_status', 'paid')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Check plan limits.
     */
    public function checkPlanLimits(Event $event): array
    {
        $subscription = $event->subscription;
        $limits = [];

        // Use -1 to represent "unlimited" (JSON-safe alternative to PHP_INT_MAX)
        $unlimited = -1;

        if (!$subscription || !$subscription->isActive()) {
            $freeTier = config('partyplanner.free_tier', []);
            $limits = [
                'max_guests' => $freeTier['max_guests'] ?? 10,
                'max_collaborators' => $freeTier['max_collaborators'] ?? 1,
                'max_photos' => $freeTier['max_photos'] ?? 5,
                'current_guests' => $event->guests()->count(),
                'current_collaborators' => $event->collaborators()->count(),
                'current_photos' => $event->photos()->count(),
                'plan' => 'free',
            ];
        } else {
            $plan = PlanType::tryFrom($subscription->plan_type);
            $maxPhotos = $plan === PlanType::PRO ? $unlimited : config('partyplanner.free_tier.max_photos', 50);
            $maxCollaborators = $plan === PlanType::PRO ? $unlimited : ($plan?->maxCollaborators() ?? 2);

            $limits = [
                'max_guests' => $subscription->guest_count ?? 50,
                'max_collaborators' => $maxCollaborators,
                'max_photos' => $maxPhotos,
                'current_guests' => $event->guests()->count(),
                'current_collaborators' => $event->collaborators()->count(),
                'current_photos' => $event->photos()->count(),
                'plan' => $subscription->plan_type,
            ];
        }

        $limits['guests_remaining'] = max(0, $limits['max_guests'] - $limits['current_guests']);
        $limits['collaborators_remaining'] = $limits['max_collaborators'] === $unlimited
            ? $unlimited
            : max(0, $limits['max_collaborators'] - $limits['current_collaborators']);
        $limits['photos_remaining'] = $limits['max_photos'] === $unlimited
            ? $unlimited
            : max(0, $limits['max_photos'] - $limits['current_photos']);

        return $limits;
    }

    /**
     * Get subscription statistics.
     */
    public function getStatistics(): array
    {
        $subscriptions = Subscription::all();

        return [
            'total' => $subscriptions->count(),
            'active' => $subscriptions->filter(fn($s) => $s->isActive())->count(),
            'pending' => $subscriptions->where('payment_status', 'pending')->count(),
            'expired' => $subscriptions->filter(fn($s) => $s->isExpired())->count(),
            'by_plan' => $subscriptions->groupBy('plan_type')->map->count()->toArray(),
            'total_revenue' => $subscriptions->where('payment_status', 'paid')->sum('total_price'),
        ];
    }

    // ========================================
    // Dynamic Plans Methods (New System)
    // ========================================

    /**
     * Create a trial subscription for a new user.
     */
    public function createTrialSubscription(User $user): ?Subscription
    {
        // Check if user already has a subscription
        $existingSubscription = $user->subscriptions()
            ->whereNull('event_id')
            ->first();

        if ($existingSubscription) {
            return null; // Already has an account-level subscription
        }

        // Get the trial plan
        $trialPlan = Plan::active()->trial()->first();

        if (!$trialPlan) {
            return null; // No trial plan configured
        }

        return Subscription::create([
            'user_id' => $user->id,
            'event_id' => null, // Account-level subscription
            'plan_id' => $trialPlan->id,
            'plan_type' => $trialPlan->slug, // Use plan slug for consistency
            'base_price' => 0,
            'guest_count' => $trialPlan->getGuestsLimit(),
            'guest_price_per_unit' => 0,
            'total_price' => 0,
            'payment_status' => 'paid', // Trial is immediately active
            'creations_used' => 0,
            'status' => 'trial',
            'starts_at' => now(),
            'expires_at' => now()->addDays($trialPlan->duration_days),
        ]);
    }

    /**
     * Create a subscription with a dynamic Plan.
     */
    public function createSubscriptionWithPlan(User $user, Plan $plan, ?Event $event = null): Subscription
    {
        $isAccountLevel = $event === null;

        // Cancel any existing active subscription if account-level
        if ($isAccountLevel) {
            $user->subscriptions()
                ->whereNull('event_id')
                ->where('status', '!=', 'cancelled')
                ->update(['status' => 'cancelled']);
        }

        return Subscription::create([
            'user_id' => $user->id,
            'event_id' => $event?->id,
            'plan_id' => $plan->id,
            'plan_type' => $plan->slug,
            'base_price' => $plan->price,
            'guest_count' => $plan->getGuestsLimit(),
            'guest_price_per_unit' => 0,
            'total_price' => $plan->price,
            'payment_status' => $plan->is_trial ? 'paid' : 'pending',
            'creations_used' => 0,
            'status' => $plan->is_trial ? 'trial' : 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays($plan->duration_days),
        ]);
    }

    /**
     * Upgrade subscription to a new dynamic Plan.
     */
    public function upgradeToPlan(Subscription $subscription, Plan $newPlan): Subscription
    {
        $oldPlan = $subscription->plan;
        $priceDifference = $newPlan->price - ($oldPlan?->price ?? 0);

        // Keep creations_used for continuity
        $subscription->update([
            'plan_id' => $newPlan->id,
            'plan_type' => $newPlan->slug,
            'base_price' => $newPlan->price,
            'guest_count' => $newPlan->getGuestsLimit(),
            'total_price' => $newPlan->price,
            'payment_status' => $priceDifference > 0 ? 'pending' : 'paid',
            'status' => 'active',
            'expires_at' => now()->addDays($newPlan->duration_days),
        ]);

        return $subscription->fresh();
    }

    /**
     * Get user's active account-level subscription.
     */
    public function getUserActiveSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->whereNull('event_id')
            ->where(function ($query) {
                $query->where('status', 'active')
                      ->orWhere('status', 'trial')
                      ->orWhere('payment_status', 'paid');
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->with('plan')
            ->latest()
            ->first();
    }

    /**
     * Handle expired subscriptions.
     */
    public function handleExpiration(): int
    {
        $count = Subscription::where('expires_at', '<', now())
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->update(['status' => 'expired']);

        return $count;
    }

    /**
     * Reset creations_used at billing period start (for renewals).
     */
    public function resetCreationsUsed(Subscription $subscription): void
    {
        $subscription->update(['creations_used' => 0]);
    }
}
