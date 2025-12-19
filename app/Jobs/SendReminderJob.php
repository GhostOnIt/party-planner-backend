<?php

namespace App\Jobs;

use App\Mail\ReminderMail;
use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Guest $guest
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if guest has email
        if (empty($this->guest->email)) {
            Log::warning("SendReminderJob: Guest {$this->guest->id} has no email address");
            return;
        }

        // Check if guest hasn't already responded
        if ($this->guest->rsvp_status !== 'pending') {
            Log::info("SendReminderJob: Guest {$this->guest->id} has already responded, skipping reminder");
            return;
        }

        // Check if invitation was sent
        if (!$this->guest->invitation_sent_at) {
            Log::warning("SendReminderJob: Guest {$this->guest->id} hasn't received invitation yet");
            return;
        }

        try {
            // Send the reminder email
            Mail::to($this->guest->email)
                ->send(new ReminderMail($this->guest));

            // Update reminder sent timestamp
            $this->guest->update(['reminder_sent_at' => now()]);

            Log::info("SendReminderJob: Reminder sent to {$this->guest->email} for event {$this->guest->event_id}");
        } catch (\Exception $e) {
            Log::error("SendReminderJob: Failed to send reminder to {$this->guest->email}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendReminderJob: Job failed for guest {$this->guest->id}: " . $exception->getMessage());
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'reminder',
            'guest:' . $this->guest->id,
            'event:' . $this->guest->event_id,
        ];
    }
}
