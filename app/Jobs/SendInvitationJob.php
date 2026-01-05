<?php

namespace App\Jobs;

use App\Mail\InvitationMail;
use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendInvitationJob implements ShouldQueue
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
            Log::warning("SendInvitationJob: Guest {$this->guest->id} has no email address");
            return;
        }

        // Check if invitation exists
        $invitation = $this->guest->invitation;

        if (!$invitation) {
            Log::warning("SendInvitationJob: Guest {$this->guest->id} has no invitation");
            return;
        }

        try {
             try {
                 $mailer = Mail::mailer();
                
                $result = Mail::to($this->guest->email)
                    ->send(new InvitationMail($this->guest, $invitation));
                
                 if ($result === null || $result === 0) {
                    throw new \Exception("Mail::send() returned null or 0, email may not have been sent");
                }
            } catch (TransportExceptionInterface $e) {
                Log::error("SendInvitationJob: SMTP transport error - " . $e->getMessage(), [
                    'guest_id' => $this->guest->id,
                    'guest_email' => $this->guest->email,
                    'error_code' => $e->getCode(),
                    'previous_exception' => $e->getPrevious()?->getMessage(),
                ]);
                throw $e;  
            } catch (\Exception $e) {
                Log::error("SendInvitationJob: Exception during send - " . $e->getMessage(), [
                    'guest_id' => $this->guest->id,
                    'guest_email' => $this->guest->email,
                    'exception_class' => get_class($e),
                    'error_code' => $e->getCode(),
                ]);
                throw $e;
            }

             $this->guest->update(['invitation_sent_at' => now()]);
            $invitation->markAsSent();

            Log::info("SendInvitationJob: Invitation sent to {$this->guest->email} for event {$this->guest->event_id}");
        } catch (\Exception $e) {
            Log::error("SendInvitationJob: Failed to send invitation to {$this->guest->email}: " . $e->getMessage());
            throw $e;     }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendInvitationJob: Job failed for guest {$this->guest->id}: " . $exception->getMessage());
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'invitation',
            'guest:' . $this->guest->id,
            'event:' . $this->guest->event_id,
        ];
    }
}
