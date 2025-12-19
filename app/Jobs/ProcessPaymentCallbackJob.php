<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public string $provider,
        public array $data
    ) {}

    public function handle(PaymentService $paymentService): void
    {
        Log::info("ProcessPaymentCallbackJob: Processing {$this->provider} callback", $this->data);

        match ($this->provider) {
            'mtn' => $paymentService->processMtnCallback($this->data),
            'airtel' => $paymentService->processAirtelCallback($this->data),
            default => Log::warning("ProcessPaymentCallbackJob: Unknown provider {$this->provider}"),
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessPaymentCallbackJob failed for {$this->provider}: {$exception->getMessage()}", [
            'data' => $this->data,
        ]);
    }

    public function tags(): array
    {
        return ['payment-callback', 'provider:' . $this->provider];
    }
}
