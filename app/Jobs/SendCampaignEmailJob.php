<?php

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\Guest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Guest $guest,
        public string $subject,
        public string $message
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->guest->email)) {
            return;
        }

        try {
            Mail::to($this->guest->email)->send(new CampaignMail($this->subject, $this->message));
        } catch (\Exception $e) {
            Log::error("Failed to send campaign email to guest {$this->guest->id}: " . $e->getMessage());
        }
    }
}
