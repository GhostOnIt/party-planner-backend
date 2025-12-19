<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEventReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Event $event,
        public int $daysUntilEvent
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        if (!$this->event->exists) {
            Log::warning("SendEventReminderJob: Event {$this->event->id} no longer exists");
            return;
        }

        // Skip if event is cancelled or completed
        if (in_array($this->event->status, ['cancelled', 'completed'])) {
            Log::info("SendEventReminderJob: Event {$this->event->id} is {$this->event->status}, skipping");
            return;
        }

        $this->event->load('user');

        $notificationService->sendEventReminder($this->event, $this->daysUntilEvent);

        Log::info("SendEventReminderJob: Reminder sent for event {$this->event->id}, {$this->daysUntilEvent} days until event");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendEventReminderJob failed for event {$this->event->id}: {$exception->getMessage()}");
    }

    public function tags(): array
    {
        return ['event-reminder', 'event:' . $this->event->id, 'user:' . $this->event->user_id];
    }
}
