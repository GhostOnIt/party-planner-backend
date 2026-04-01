<?php

namespace App\Observers;

use App\Models\Subscription;
use App\Services\SubscriptionService;

class SubscriptionObserver
{
    /**
     * Handle the Subscription "created" event.
     */
    public function created(Subscription $subscription): void
    {
        $subscriptionService = app()->make(SubscriptionService::class);

        if (method_exists($subscriptionService, 'syncAccountEventLimits')) {
            $subscriptionService->syncAccountEventLimits($subscription);
        }
    }

    /**
     * Handle the Subscription "updated" event.
     */
    public function updated(Subscription $subscription): void
    {
        if (! $subscription->wasChanged([
            'plan_id',
            'plan_type',
            'payment_status',
            'status',
            'expires_at',
        ])) {
            return;
        }

        $subscriptionService = app()->make(SubscriptionService::class);

        if (method_exists($subscriptionService, 'syncAccountEventLimits')) {
            $subscriptionService->syncAccountEventLimits($subscription);
        }
    }
}
