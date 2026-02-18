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
     * Replace variables in text with guest data.
     */
    private function replaceVariables(string $text): string
    {
        $replacements = [
            '{nom}' => $this->guest->name ?? '',
            '{name}' => $this->guest->name ?? '',
            '{mail}' => $this->guest->email ?? '',
            '{email}' => $this->guest->email ?? '',
            '{numero}' => $this->guest->phone ?? '',
            '{phone}' => $this->guest->phone ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->guest->email)) {
            return;
        }

        $subject = $this->replaceVariables($this->subject);
        $message = $this->replaceVariables($this->message);

        try {
            Mail::to($this->guest->email)->send(new CampaignMail($subject, $message));
        } catch (\Exception $e) {
            Log::error("Failed to send campaign email to guest {$this->guest->id}: " . $e->getMessage());
        }
    }
}
