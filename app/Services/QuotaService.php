<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\TopUp;
use App\Models\User;

class QuotaService
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    /**
     * Get creations quota info for user.
     */
    public function getCreationsQuota(User $user): array
    {
        $subscription = $this->getActiveSubscription($user);

        if (!$subscription) {
            return [
                'base_quota' => 0,
                'topup_credits' => 0,
                'total_quota' => 0,
                'used' => 0,
                'remaining' => 0,
                'is_unlimited' => false,
                'percentage_used' => 100,
                'can_create' => false,
            ];
        }

        $baseQuota = $subscription->plan 
            ? $subscription->plan->getEventsCreationLimit() 
            : 1;

        $isUnlimited = $baseQuota === -1;
        $topUpCredits = $this->getTopUpCredits($user, $subscription);
        $used = $subscription->creations_used ?? 0;
        
        $totalQuota = $isUnlimited ? -1 : $baseQuota + $topUpCredits;
        $remaining = $isUnlimited ? -1 : max(0, $totalQuota - $used);

        return [
            'base_quota' => $baseQuota,
            'topup_credits' => $topUpCredits,
            'total_quota' => $totalQuota,
            'used' => $used,
            'remaining' => $remaining,
            'is_unlimited' => $isUnlimited,
            'percentage_used' => $isUnlimited ? 0 : ($totalQuota > 0 
                ? round(($used / $totalQuota) * 100) 
                : 100),
            'can_create' => $isUnlimited || $remaining > 0,
        ];
    }

    /**
     * Check if user can create an event.
     */
    public function canCreateEvent(User $user): bool
    {
        $quota = $this->getCreationsQuota($user);
        return $quota['can_create'];
    }

    /**
     * Consume a creation credit (decrement).
     */
    public function consumeCreation(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);

        if (!$subscription) {
            return false;
        }

        // Check if can create
        if (!$this->canCreateEvent($user)) {
            return false;
        }

        // Increment used count
        $subscription->increment('creations_used');

        return true;
    }

    /**
     * Get quota percentage for alerts (80%, 90%).
     */
    public function getQuotaPercentage(User $user): int
    {
        $quota = $this->getCreationsQuota($user);
        return $quota['percentage_used'];
    }

    /**
     * Check if user should be warned about quota.
     */
    public function shouldWarnAboutQuota(User $user): ?string
    {
        $percentage = $this->getQuotaPercentage($user);

        if ($percentage >= 100) {
            return 'quota_reached';
        }
        if ($percentage >= 90) {
            return 'quota_90';
        }
        if ($percentage >= 80) {
            return 'quota_80';
        }

        return null;
    }

    /**
     * Get top-up credits for user's current subscription period.
     */
    protected function getTopUpCredits(User $user, ?Subscription $subscription = null): int
    {
        if (!$subscription) {
            return 0;
        }

        return TopUp::where('user_id', $user->id)
            ->where(function ($query) use ($subscription) {
                $query->where('subscription_id', $subscription->id)
                      ->orWhereNull('subscription_id');
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->sum('credits');
    }

    /**
     * Get active subscription for user.
     */
    protected function getActiveSubscription(User $user): ?Subscription
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
     * Add top-up credits to user.
     */
    public function addTopUp(User $user, int $credits, int $price = 0): TopUp
    {
        $subscription = $this->getActiveSubscription($user);

        return TopUp::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'credits' => $credits,
            'price' => $price,
            'purchased_at' => now(),
            'expires_at' => $subscription?->expires_at,
        ]);
    }
}

