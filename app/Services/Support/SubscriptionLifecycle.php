<?php

namespace App\Services\Support;

use App\Models\Subscription;

/**
 * Calculs purs autour du cycle de vie d'une souscription (phase, jours restants,
 * grace period, restriction). Extrait de EntitlementService / SubscriptionService
 * qui contenaient la même logique en double.
 */
final class SubscriptionLifecycle
{
    /**
     * @return array<string, mixed>
     */
    public static function buildPayload(?Subscription $subscription): array
    {
        if (!$subscription) {
            return [
                'phase' => 'no_subscription',
                'days_to_expiry' => null,
                'grace_days_elapsed' => null,
                'archive_in_days' => null,
                'is_restricted' => false,
                'is_archived' => false,
            ];
        }

        $now = now();
        $expiresAt = $subscription->expires_at;
        $daysToExpiry = $expiresAt ? (int) floor($now->diffInDays($expiresAt, false)) : null;
        $graceDaysElapsed = $subscription->grace_started_at
            ? (int) floor($subscription->grace_started_at->diffInDays($now))
            : null;

        $phase = self::resolvePhase($subscription, $daysToExpiry, $expiresAt);

        return [
            'phase' => $phase,
            'days_to_expiry' => $daysToExpiry,
            'grace_days_elapsed' => $graceDaysElapsed,
            'archive_in_days' => $graceDaysElapsed === null ? null : max(0, 90 - $graceDaysElapsed),
            'is_restricted' => in_array($phase, ['grace_period', 'archived', 'expired'], true),
            'is_archived' => $phase === 'archived',
        ];
    }

    private static function resolvePhase(Subscription $subscription, ?int $daysToExpiry, mixed $expiresAt): string
    {
        if ($subscription->status === 'archived_restricted') {
            return 'archived';
        }
        if ($subscription->status === 'grace_period') {
            return 'grace_period';
        }
        if ($daysToExpiry !== null && $daysToExpiry <= 1 && $daysToExpiry >= 0) {
            return 'renewal_last_day';
        }
        if ($daysToExpiry !== null && $daysToExpiry <= 7 && $daysToExpiry >= 0) {
            return 'renewal_due';
        }
        if ($expiresAt && $expiresAt->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
