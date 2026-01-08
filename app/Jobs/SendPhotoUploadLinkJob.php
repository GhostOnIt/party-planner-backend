<?php

namespace App\Jobs;

use App\Mail\PhotoUploadMail;
use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendPhotoUploadLinkJob implements ShouldQueue
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
            Log::warning("SendPhotoUploadLinkJob: Guest {$this->guest->id} has no email address");
            return;
        }

        // Check if guest is checked in
        if (!$this->guest->checked_in) {
            Log::warning("SendPhotoUploadLinkJob: Guest {$this->guest->id} is not checked in");
            return;
        }

        // Check if guest has photo upload token
        if (empty($this->guest->photo_upload_token)) {
            Log::warning("SendPhotoUploadLinkJob: Guest {$this->guest->id} has no photo upload token");
            return;
        }

        try {
            try {
                $mailer = Mail::mailer();
                
                $result = Mail::to($this->guest->email)
                    ->send(new PhotoUploadMail($this->guest));
                
                if ($result === null || $result === 0) {
                    throw new \Exception("Mail::send() returned null or 0, email may not have been sent");
                }

                Log::info("SendPhotoUploadLinkJob: Photo upload link sent to {$this->guest->email} for event {$this->guest->event_id}");
            } catch (TransportExceptionInterface $e) {
                Log::error("SendPhotoUploadLinkJob: SMTP transport error - " . $e->getMessage(), [
                    'guest_id' => $this->guest->id,
                    'guest_email' => $this->guest->email,
                    'error_code' => $e->getCode(),
                    'previous_exception' => $e->getPrevious()?->getMessage(),
                ]);
                throw $e;
            } catch (\Exception $e) {
                Log::error("SendPhotoUploadLinkJob: Failed to send photo upload link to {$this->guest->email}: " . $e->getMessage());
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error("SendPhotoUploadLinkJob: Job failed for guest {$this->guest->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendPhotoUploadLinkJob: Job failed for guest {$this->guest->id}: " . $exception->getMessage());
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'photo-upload-link',
            'guest:' . $this->guest->id,
            'event:' . $this->guest->event_id,
        ];
    }
}
