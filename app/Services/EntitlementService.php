<?php

namespace App\Services;

use App\Models\Event;
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
     * Uses "maximum généreux" approach: OR between stored event features and current account subscription.
     * If feature is enabled in event OR in current account subscription, returns true.
     */
    public function can(User $user, string $feature, ?Event $event = null): bool
    {
        // Get feature stored on the event (from creation time)
        $storedFeature = false;
        if ($event && $event->features_enabled !== null) {
            $storedFeature = $event->features_enabled[$feature] ?? false;
        }

        // Get feature from current account-level subscription
        $subscription = $this->getActiveSubscription($user);
        $currentFeature = false;

        if ($subscription && $subscription->plan) {
            $currentFeature = $subscription->plan->hasFeature($feature);
        } else {
            $currentFeature = $this->defaultFeatures[$feature] ?? false;
        }

        // OR: if enabled in event OR in current subscription, return true
        return $storedFeature || $currentFeature;
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
     * Get effective limit for an event using "maximum généreux" approach.
     * Returns the MAX between stored event limit and current account subscription limit.
     * This ensures events benefit from account upgrades while keeping their original limits.
     * Returns -1 for unlimited.
     */
    public function getEffectiveLimit(Event $event, User $user, string $limitKey): int
    {
        // Get limit stored on the event (from creation time)
        $storedLimit = match($limitKey) {
            'guests.max_per_event' => $event->max_guests_allowed ?? 0,
            'collaborators.max_per_event' => $event->max_collaborators_allowed ?? 0,
            'photos.max_per_event' => $event->max_photos_allowed ?? 0,
            default => 0,
        };

        // Get limit from current account-level subscription
        $currentLimit = $this->limit($user, $limitKey);

        // MAX: take the best of both (generous approach)
        // -1 means unlimited, so max(-1, anything) = -1 (unlimited)
        if ($storedLimit === -1 || $currentLimit === -1) {
            return -1;
        }

        return max($storedLimit, $currentLimit);
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
}

