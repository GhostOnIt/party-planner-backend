<?php

namespace App\Jobs;

use App\Mail\EventCreatedForYouPendingMail;
use App\Models\EventCreationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEventCreatedForYouPendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public EventCreationInvitation $invitation
    ) {}

    public function handle(): void
    {
        try {
            if (!$this->invitation->exists) {
                Log::warning("SendEventCreatedForYouPendingJob: Invitation {$this->invitation->id} no longer exists");
                return;
            }

            $this->invitation->load(['event', 'admin']);

            if (!$this->invitation->event || !$this->invitation->admin) {
                Log::error("SendEventCreatedForYouPendingJob: Event or admin not loaded for invitation {$this->invitation->id}");
                return;
            }

            $email = $this->invitation->email;
            Log::info("SendEventCreatedForYouPendingJob: Sending event created for you email to {$email}");

            Mail::to($email)->send(new EventCreatedForYouPendingMail($this->invitation));

            Log::info("SendEventCreatedForYouPendingJob: Email sent successfully to {$email}");
        } catch (\Exception $e) {
            Log::error("SendEventCreatedForYouPendingJob: Error for invitation {$this->invitation->id}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendEventCreatedForYouPendingJob failed for invitation {$this->invitation->id}: {$exception->getMessage()}");
    }

    public function tags(): array
    {
        return [
            'event-created-for-you-pending',
            'invitation:' . $this->invitation->id,
            'event:' . $this->invitation->event_id,
        ];
    }
}
