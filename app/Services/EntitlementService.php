<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class EntitlementService
{
    /**
     * Default limits for users without subscription.
     */
    protected array $defaultLimits = [
        'events.creations_per_billing_period' => 0,
        'guests.max_per_event' => 10,
        'collaborators.max_per_event' => 1,
        'photos.max_per_event' => 5,
    ];

    /**
     * Default features for users without subscription.
     */
    protected array $defaultFeatures = [
        'budget.enabled' => false,
        'planning.enabled' => false,
        'tasks.enabled' => false,
        'guests.manage' => false,
        'guests.import' => false,
        'guests.export' => false,
        'invitations.sms' => false,
        'invitations.whatsapp' => false,
        'collaborators.manage' => false,
        'roles_permissions.enabled' => false,
        'exports.pdf' => false,
        'exports.excel' => false,
        'exports.csv' => false,
        'history.enabled' => false,
        'reporting.enabled' => false,
        'branding.custom' => false,
        'support.whatsapp_priority' => false,
        'support.dedicated' => false,
        'multi_client.enabled' => false,
        'assistance.human' => false,
    ];

    /**
     * Check if user has a specific feature.
     * For events, checks if the feature was enabled at event creation time.
     */
    public function can(User $user, string $feature, ?\App\Models\Event $event = null): bool
    {
        // If checking for a specific event, use features_enabled stored on the event
        // This allows events created during an active subscription to keep their features
        // even after the subscription expires.
        if ($event && $event->features_enabled !== null) {
            return $event->features_enabled[$feature] ?? false;
        }

        // Otherwise, check current subscription
        $subscription = $this->getActiveSubscription($user);

        if (!$subscription || !$subscription->plan) {
            return $this->defaultFeatures[$feature] ?? false;
        }

        return $subscription->plan->hasFeature($feature);
    }

    /**
     * Get limit value for user.
     * Returns -1 for unlimited.
     */
    public function limit(User $user, string $limitKey): int
    {
        $subscription = $this->getActiveSubscription($user);

        if (!$subscription || !$subscription->plan) {
            return $this->defaultLimits[$limitKey] ?? 0;
        }

        return $subscription->plan->getLimit($limitKey, $this->defaultLimits[$limitKey] ?? 0);
    }

    /**
     * Check if a limit is unlimited for user.
     */
    public function isUnlimited(User $user, string $limitKey): bool
    {
        return $this->limit($user, $limitKey) === -1;
    }

    /**
     * Get all effective entitlements for user.
     */
    public function getEffectiveEntitlements(User $user): array
    {
        $subscription = $this->getActiveSubscription($user);

        if (!$subscription || !$subscription->plan) {
            return [
                'plan' => null,
                'subscription' => null,
                'limits' => $this->defaultLimits,
                'features' => $this->defaultFeatures,
                'is_active' => false,
                'is_trial' => false,
            ];
        }

        $plan = $subscription->plan;

        return [
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ],
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status ?? 'active',
                'starts_at' => $subscription->starts_at,
                'expires_at' => $subscription->expires_at,
            ],
            'limits' => array_merge($this->defaultLimits, $plan->getLimitsArray()),
            'features' => array_merge($this->defaultFeatures, $plan->getFeaturesArray()),
            'is_active' => true,
            'is_trial' => $plan->is_trial,
        ];
    }

    /**
     * Assert user can use a feature, throw exception if not.
     */
    public function assertCan(User $user, string $feature, ?string $message = null): void
    {
        if (!$this->can($user, $feature)) {
            $message = $message ?? "Feature '{$feature}' is not available with your current plan.";
            throw new \Exception($message);
        }
    }

    /**
     * Assert user is within a limit.
     */
    public function assertWithinLimit(User $user, string $limitKey, int $currentUsage, ?string $message = null): void
    {
        $limit = $this->limit($user, $limitKey);

        // -1 means unlimited
        if ($limit === -1) {
            return;
        }

        if ($currentUsage >= $limit) {
            $message = $message ?? "Limit reached for '{$limitKey}'. Your plan allows {$limit}.";
            throw new \Exception($message);
        }
    }

    /**
     * Get active subscription for user (account-level).
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
     * Check if user has any active subscription.
     */
    public function hasActiveSubscription(User $user): bool
    {
        return $this->getActiveSubscription($user) !== null;
    }

    /**
     * Get user's current plan or null.
     */
    public function getCurrentPlan(User $user): ?Plan
    {
        $subscription = $this->getActiveSubscription($user);
        return $subscription?->plan;
    }

    /**
     * Get effective limit using MAX between stored event limit and current subscription limit.
     * This uses the "maximum généreux" approach: events keep their higher limit even if
     * subscription changes later.
     *
     * @param \App\Models\Event $event
     * @param User $user
     * @param string $limitKey The limit key (e.g., 'photos.max_per_event')
     * @return int The effective limit, or -1 for unlimited
     */
    public function getEffectiveLimit(\App\Models\Event $event, User $user, string $limitKey): int
    {
        // Get current subscription limit
        $subscriptionLimit = $this->limit($user, $limitKey);

        // Get stored event limit based on limit key
        $eventLimit = null;
        
        if ($limitKey === 'photos.max_per_event') {
            $eventLimit = $event->max_photos_allowed;
        } elseif ($limitKey === 'guests.max_per_event') {
            $eventLimit = $event->max_guests_allowed;
        } elseif ($limitKey === 'collaborators.max_per_event') {
            $eventLimit = $event->max_collaborators_allowed;
        }

        // Use MAX between stored and current subscription (if stored exists)
        if ($eventLimit !== null && $eventLimit > 0) {
            // If subscription is unlimited, return unlimited
            if ($subscriptionLimit === -1) {
                return -1;
            }
            // Return the maximum between stored and subscription
            return max($eventLimit, $subscriptionLimit);
        }

        // If no stored limit, use subscription limit
        return $subscriptionLimit;
    }
}

