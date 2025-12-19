<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\BudgetService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBudgetAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Event $event,
        public string $alertType = 'threshold'
    ) {}

    public function handle(BudgetService $budgetService, NotificationService $notificationService): void
    {
        if (!$this->event->exists) {
            Log::warning("SendBudgetAlertJob: Event {$this->event->id} no longer exists");
            return;
        }

        $stats = $budgetService->getStatistics($this->event);

        // Determine if we should send the alert
        $threshold = config('partyplanner.notifications.budget_alert_threshold', 90);
        $shouldSend = false;
        $percentage = $stats['budget_used_percent'];

        if ($this->alertType === 'threshold' && $percentage >= $threshold) {
            $shouldSend = true;
        } elseif ($this->alertType === 'over_budget' && $stats['is_over_budget']) {
            $shouldSend = true;
            $percentage = 100 + abs($stats['variance_percent']);
        }

        if (!$shouldSend) {
            Log::info("SendBudgetAlertJob: Conditions not met for event {$this->event->id}");
            return;
        }

        $notificationService->sendBudgetAlert($this->event, $this->alertType, $percentage);

        Log::info("SendBudgetAlertJob: Alert sent for event {$this->event->id}, type: {$this->alertType}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendBudgetAlertJob failed for event {$this->event->id}: {$exception->getMessage()}");
    }

    public function tags(): array
    {
        return ['budget-alert', 'event:' . $this->event->id, 'user:' . $this->event->user_id];
    }
}
