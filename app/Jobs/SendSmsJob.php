<?php

namespace App\Jobs;

use App\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected string $to,
        protected string $message,
        protected ?string $from = null
    ) {}

    public function handle(TwilioService $twilio): void
    {
        $result = $twilio->sendSms($this->to, $this->message, $this->from);

        if (!$result['success']) {
            Log::warning('SMS job failed', [
                'to' => $this->to,
                'error' => $result['message'] ?? 'Unknown error',
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff * $this->attempts());
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SMS job failed permanently', [
            'to' => $this->to,
            'error' => $exception->getMessage(),
        ]);
    }
}
