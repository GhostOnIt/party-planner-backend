<?php

namespace App\Jobs;

use App\Enums\EventStatus;
use App\Mail\EventStatusChangeMail;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyGuestsOfStatusChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @param  string  $eventId  The event ID (UUID) when the status changed
     * @param  string  $statusValue  The status value that triggered the notification (upcoming, ongoing, completed, cancelled)
     */
    public function __construct(
        public string $eventId,
        public string $statusValue
    ) {}

    /**
     * Execute the job.
     * Re-loads the event and only sends notifications if the status is still the same
     * (organizer can revert within 2 minutes).
     */
    public function handle(): void
    {
        $event = Event::find($this->eventId);

        if (!$event) {
            Log::warning("NotifyGuestsOfStatusChangeJob: Event {$this->eventId} not found");

            return;
        }

        $currentStatus = $event->status;

        if ($currentStatus !== $this->statusValue) {
            Log::info("NotifyGuestsOfStatusChangeJob: Status changed since dispatch (expected {$this->statusValue}, got {$currentStatus}), skipping notifications for event {$this->eventId}");

            return;
        }

        $status = EventStatus::tryFrom($this->statusValue);
        if (!$status) {
            Log::warning("NotifyGuestsOfStatusChangeJob: Invalid status value {$this->statusValue}");

            return;
        }

        $guests = $event->guests()->with('invitation')->get();

        foreach ($guests as $guest) {
            if (empty($guest->email)) {
                Log::warning("NotifyGuestsOfStatusChangeJob: Guest {$guest->id} has no email, skipping");

                continue;
            }

            try {
                Mail::to($guest->email)->send(new EventStatusChangeMail($guest, $event, $status));
                Log::info("NotifyGuestsOfStatusChangeJob: Status change notification sent to {$guest->email} for event {$event->id}");
            } catch (\Throwable $e) {
                Log::error("NotifyGuestsOfStatusChangeJob: Failed to send to {$guest->email}: {$e->getMessage()}");
            }
        }
    }
}
