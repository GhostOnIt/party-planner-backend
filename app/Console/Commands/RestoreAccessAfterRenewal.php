<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\ActivityService;
use Illuminate\Console\Command;

class RestoreAccessAfterRenewal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:restore-access-after-renewal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restaure l’accès complet après renouvellement payé';

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
            ->whereIn('status', ['grace_period', 'archived_restricted', 'expired'])
            ->where('payment_status', 'paid')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        $restored = 0;
        foreach ($subscriptions as $subscription) {
            $subscription->update([
                'status' => 'active',
                'grace_started_at' => null,
                'archived_at' => null,
                'non_renewal_reason' => null,
            ]);

            $this->activityService->logAction(
                action: 'subscription_restored_after_renewal',
                description: 'Accès abonnement restauré après renouvellement.',
                model: $subscription
            );

            $restored++;
        }

        $this->info("Abonnements restaurés: {$restored}");

        return self::SUCCESS;
    }
}

