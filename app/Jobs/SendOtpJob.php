<?php

namespace App\Jobs;

use App\Models\Otp;
use App\Services\OtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Otp $otp
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OtpService $otpService): void
    {
        // Check if OTP is still valid
        if (!$this->otp->isValid()) {
            Log::info('SendOtpJob: OTP is no longer valid, skipping', [
                'otp_id' => $this->otp->id,
            ]);
            return;
        }

        try {
            $result = $otpService->send($this->otp);

            if (!$result['success']) {
                Log::warning('SendOtpJob: Failed to send OTP', [
                    'otp_id' => $this->otp->id,
                    'channel' => $this->otp->channel,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);

                // Re-throw to trigger retry
                if ($this->attempts() < $this->tries) {
                    throw new \Exception($result['message'] ?? 'Failed to send OTP');
                }
            }

            Log::info('SendOtpJob: OTP sent successfully', [
                'otp_id' => $this->otp->id,
                'channel' => $this->otp->channel,
            ]);

        } catch (\Exception $e) {
            Log::error('SendOtpJob: Exception occurred', [
                'otp_id' => $this->otp->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendOtpJob: Job failed permanently', [
            'otp_id' => $this->otp->id,
            'channel' => $this->otp->channel,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'otp',
            'otp:' . $this->otp->id,
            'channel:' . $this->otp->channel,
            'type:' . $this->otp->type,
        ];
    }
}
