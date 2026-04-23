<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\ActivityService;
use Illuminate\Console\Command;

class SendSubscriptionRenewalReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-renewal-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie (ou marque) les rappels J-7 et J-1 avant expiration';

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
        $now = now();

        $subscriptions = Subscription::query()
            ->whereNull('event_id')
            ->whereIn('status', ['active', 'trial', 'renewal_due'])
            ->where('payment_status', 'paid')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->with(['user', 'plan'])
            ->get();

        $j7 = 0;
        $j1 = 0;

        foreach ($subscriptions as $subscription) {
            $daysToExpiry = (int) floor($now->diffInDays($subscription->expires_at, false));

            if ($daysToExpiry <= 7 && $daysToExpiry >= 2 && !$subscription->renewal_reminder_sent_at) {
                $subscription->update([
                    'renewal_reminder_sent_at' => $now,
                    'status' => 'renewal_due',
                ]);
                $this->activityService->logAction(
                    action: 'subscription_renewal_reminder_sent',
                    description: 'Rappel de renouvellement envoyé (J-7)',
                    model: $subscription,
                    metadata: ['stage' => 'J-7', 'days_to_expiry' => $daysToExpiry]
                );
                $j7++;
            }

            if ($daysToExpiry <= 1 && $daysToExpiry >= 0 && !$subscription->final_reminder_sent_at) {
                $subscription->update([
                    'final_reminder_sent_at' => $now,
                    'status' => 'renewal_due',
                ]);
                $this->activityService->logAction(
                    action: 'subscription_final_reminder_sent',
                    description: 'Rappel final de renouvellement envoyé (J-1)',
                    model: $subscription,
                    metadata: ['stage' => 'J-1', 'days_to_expiry' => $daysToExpiry]
                );
                $j1++;
            }
        }

        $this->info("Rappels envoyés: J-7={$j7}, J-1={$j1}");

        return self::SUCCESS;
    }
}

