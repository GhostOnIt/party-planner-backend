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
        $this->collaborator->load(['user', 'event.user']);

        // Send email
        if ($this->collaborator->user->email) {
            Mail::to($this->collaborator->user->email)
                ->send(new CollaborationInvitationMail($this->collaborator));

            Log::info("SendCollaborationInvitationJob: Email sent to {$this->collaborator->user->email}");
        }

        // Create in-app notification
        Notification::create([
            'user_id' => $this->collaborator->user_id,
            'event_id' => $this->collaborator->event_id,
            'type' => 'collaboration_invite',
            'title' => 'Invitation à collaborer',
            'message' => "{$this->collaborator->event->user->name} vous invite à collaborer sur l'événement \"{$this->collaborator->event->title}\".",
            'sent_via' => 'email',
        ]);
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
