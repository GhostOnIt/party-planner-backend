<?php

namespace App\Jobs;

use App\Models\Guest;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendGuestReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Guest $guest
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        if (!$this->guest->exists) {
            Log::warning("SendGuestReminderJob: Guest {$this->guest->id} no longer exists");
            return;
        }

        // Skip if guest has already responded (accepted or declined)
        if (in_array($this->guest->rsvp_status, ['accepted', 'declined'])) {
            Log::info("SendGuestReminderJob: Guest {$this->guest->id} already responded ({$this->guest->rsvp_status}), skipping reminder");
            return;
        }

        $this->guest->load('event.user');

        $notificationService->sendGuestReminder($this->guest);

        // Update reminder_sent_at on guest
        $this->guest->update(['reminder_sent_at' => now()]);

        Log::info("SendGuestReminderJob: Reminder sent for guest {$this->guest->id}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendGuestReminderJob failed for guest {$this->guest->id}: {$exception->getMessage()}");
    }

    public function tags(): array
    {
        return ['guest-reminder', 'guest:' . $this->guest->id, 'event:' . $this->guest->event_id];
    }
}
