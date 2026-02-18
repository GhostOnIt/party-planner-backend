<?php

namespace App\Jobs;

use App\Mail\CollaborationInvitationGuestMail;
use App\Models\CollaborationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCollaborationInvitationGuestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public CollaborationInvitation $invitation
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (!$this->invitation->exists) {
                Log::warning("SendCollaborationInvitationGuestJob: Invitation {$this->invitation->id} no longer exists");
                return;
            }

            $this->invitation->load('event.user');

            if (!$this->invitation->event || !$this->invitation->event->user) {
                Log::error("SendCollaborationInvitationGuestJob: Event or inviter not loaded for invitation {$this->invitation->id}");
                return;
            }

            $email = $this->invitation->email;
            Log::info("SendCollaborationInvitationGuestJob: Sending guest invitation email to {$email}");

            Mail::to($email)
                ->send(new CollaborationInvitationGuestMail($this->invitation));

            Log::info("SendCollaborationInvitationGuestJob: Email sent successfully to {$email}");
        } catch (\Exception $e) {
            Log::error("SendCollaborationInvitationGuestJob: Error for invitation {$this->invitation->id}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendCollaborationInvitationGuestJob failed for invitation {$this->invitation->id}: {$exception->getMessage()}");
    }

    public function tags(): array
    {
        return [
            'collaboration-invitation-guest',
            'invitation:' . $this->invitation->id,
            'event:' . $this->invitation->event_id,
        ];
    }
}
