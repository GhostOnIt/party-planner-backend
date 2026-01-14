<?php

namespace App\Jobs;

use App\Mail\CollaborationInvitationMail;
use App\Models\Collaborator;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCollaborationInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Collaborator $collaborator
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("SendCollaborationInvitationJob: Starting job for collaborator {$this->collaborator->id}");

            // Verify collaborator still exists and hasn't been accepted
            if (!$this->collaborator->exists) {
                Log::warning("SendCollaborationInvitationJob: Collaborator {$this->collaborator->id} no longer exists");
                return;
            }

            if ($this->collaborator->isAccepted()) {
                Log::info("SendCollaborationInvitationJob: Collaborator {$this->collaborator->id} already accepted");
                return;
            }

            // Load relationships
            Log::info("SendCollaborationInvitationJob: Loading relationships for collaborator {$this->collaborator->id}");
            $this->collaborator->load(['user', 'event.user', 'collaboratorRoles']);

            // Verify relationships are loaded
            if (!$this->collaborator->user) {
                Log::error("SendCollaborationInvitationJob: User relationship not loaded for collaborator {$this->collaborator->id}");
                return;
            }

            if (!$this->collaborator->event || !$this->collaborator->event->user) {
                Log::error("SendCollaborationInvitationJob: Event or event user relationship not loaded for collaborator {$this->collaborator->id}");
                return;
            }

            // Send email
            if ($this->collaborator->user->email) {
                Log::info("SendCollaborationInvitationJob: Sending email to {$this->collaborator->user->email}");

                try {
                    Mail::to($this->collaborator->user->email)
                        ->send(new CollaborationInvitationMail($this->collaborator));

                    Log::info("SendCollaborationInvitationJob: Email sent successfully to {$this->collaborator->user->email}");
                } catch (\Exception $e) {
                    Log::error("SendCollaborationInvitationJob: Failed to send email to {$this->collaborator->user->email}: {$e->getMessage()}");
                    throw $e;
                }
            } else {
                Log::warning("SendCollaborationInvitationJob: No email address for user {$this->collaborator->user_id}");
            }

            // Create in-app notification
            Log::info("SendCollaborationInvitationJob: Creating in-app notification");
            try {
                Notification::create([
                    'user_id' => $this->collaborator->user_id,
                    'event_id' => $this->collaborator->event_id,
                    'type' => 'collaboration_invite',
                    'title' => 'Invitation à collaborer',
                    'message' => "{$this->collaborator->event->user->name} vous invite à collaborer sur l'événement \"{$this->collaborator->event->title}\".",
                    'sent_via' => 'email',
                ]);

                Log::info("SendCollaborationInvitationJob: In-app notification created successfully");
            } catch (\Exception $e) {
                Log::error("SendCollaborationInvitationJob: Failed to create notification: {$e->getMessage()}");
                throw $e;
            }

            Log::info("SendCollaborationInvitationJob: Job completed successfully for collaborator {$this->collaborator->id}");

        } catch (\Exception $e) {
            Log::error("SendCollaborationInvitationJob: Unexpected error for collaborator {$this->collaborator->id}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendCollaborationInvitationJob failed for collaborator {$this->collaborator->id}: {$exception->getMessage()}");
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'collaboration-invitation',
            'collaborator:' . $this->collaborator->id,
            'event:' . $this->collaborator->event_id,
            'user:' . $this->collaborator->user_id,
        ];
    }
}
