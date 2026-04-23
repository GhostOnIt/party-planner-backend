<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\ActivityService;
use Illuminate\Console\Command;

class ArchiveExceedingResources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:archive-exceeding-resources {--after-days=90 : Délai de grâce avant archivage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive les abonnements en grâce au-delà du délai, sans suppression des données';

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
        $days = max(1, (int) $this->option('after-days'));
        $threshold = now()->subDays($days);

        $subscriptions = Subscription::query()
            ->whereNull('event_id')
            ->where('status', 'grace_period')
            ->whereNotNull('grace_started_at')
            ->where('grace_started_at', '<=', $threshold)
            ->get();

        $archived = 0;
        foreach ($subscriptions as $subscription) {
            $subscription->update([
                'status' => 'archived_restricted',
                'archived_at' => now(),
            ]);

            $this->activityService->logAction(
                action: 'subscription_archived_restricted',
                description: 'Abonnement archivé après période de grâce.',
                model: $subscription,
                metadata: ['grace_started_at' => optional($subscription->grace_started_at)?->toIso8601String()]
            );

            $archived++;
        }

        $this->info("Abonnements archivés: {$archived}");

        return self::SUCCESS;
    }
}

