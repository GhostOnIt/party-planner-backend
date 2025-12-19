<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Event $event,
        public array $guestIds,
        public ?string $customMessage = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("SendBulkInvitationsJob: Starting bulk send for event {$this->event->id} with " . count($this->guestIds) . " guests");

        $guests = Guest::whereIn('id', $this->guestIds)
            ->where('event_id', $this->event->id)
            ->whereNotNull('email')
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($guests as $guest) {
            try {
                // Update custom message if provided
                if ($this->customMessage && $guest->invitation) {
                    $guest->invitation->update(['custom_message' => $this->customMessage]);
                }

                // Dispatch individual invitation job with delay to avoid rate limiting
                SendInvitationJob::dispatch($guest)
                    ->delay(now()->addSeconds($sent * 2)); // 2 second delay between emails

                $sent++;
            } catch (\Exception $e) {
                Log::error("SendBulkInvitationsJob: Failed to queue invitation for guest {$guest->id}: " . $e->getMessage());
                $failed++;
            }
        }

        Log::info("SendBulkInvitationsJob: Completed for event {$this->event->id}. Queued: {$sent}, Failed: {$failed}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendBulkInvitationsJob: Job failed for event {$this->event->id}: " . $exception->getMessage());
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'bulk-invitation',
            'event:' . $this->event->id,
        ];
    }
}
