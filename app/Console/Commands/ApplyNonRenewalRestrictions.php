<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\ActivityService;
use Illuminate\Console\Command;

class ApplyNonRenewalRestrictions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:apply-non-renewal-restrictions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Applique le mode restreint après non-renouvellement (début période de grâce)';

    public function __construct(
        protected ActivityService $activityService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $subscriptions = Subscription::query()
            ->whereNull('event_id')
            ->whereIn('status', ['active', 'trial', 'renewal_due'])
            ->where('payment_status', 'paid')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $restricted = 0;
        foreach ($subscriptions as $subscription) {
            $subscription->update([
                'status' => 'grace_period',
                'grace_started_at' => $subscription->grace_started_at ?? now(),
                'non_renewal_reason' => $subscription->non_renewal_reason ?? 'non_renewal',
            ]);

            $this->activityService->logAction(
                action: 'subscription_grace_period_started',
                description: 'Abonnement basculé en période de grâce (non-renouvellement).',
                model: $subscription,
                metadata: ['expires_at' => optional($subscription->expires_at)?->toIso8601String()]
            );

            $restricted++;
        }

        $this->info("Abonnements basculés en grâce: {$restricted}");

        return self::SUCCESS;
    }
}

