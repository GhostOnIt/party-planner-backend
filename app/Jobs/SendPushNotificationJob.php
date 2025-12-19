<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Notification $notification
    ) {}

    public function handle(): void
    {
        if (!$this->notification->exists) {
            return;
        }

        $this->notification->load(['user', 'event']);

        // Get user's device tokens (placeholder - implement based on your FCM setup)
        $deviceTokens = $this->getDeviceTokens();

        if (empty($deviceTokens)) {
            Log::info("SendPushNotificationJob: No device tokens for user {$this->notification->user_id}");
            return;
        }

        $this->sendToFcm($deviceTokens);
    }

    protected function getDeviceTokens(): array
    {
        // Placeholder - implement device token retrieval
        // This could come from a device_tokens table or a JSON column on the user
        return [];
    }

    protected function sendToFcm(array $tokens): void
    {
        $serverKey = config('services.firebase.server_key');

        if (!$serverKey) {
            Log::warning('SendPushNotificationJob: Firebase server key not configured');
            return;
        }

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $this->notification->title,
                'body' => $this->notification->message,
                'sound' => 'default',
            ],
            'data' => [
                'notification_id' => $this->notification->id,
                'type' => $this->notification->type,
                'event_id' => $this->notification->event_id,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);

            if ($response->successful()) {
                Log::info("SendPushNotificationJob: Push sent for notification {$this->notification->id}");
            } else {
                Log::error("SendPushNotificationJob: FCM error - {$response->body()}");
            }
        } catch (\Exception $e) {
            Log::error("SendPushNotificationJob: Exception - {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendPushNotificationJob failed for notification {$this->notification->id}: {$exception->getMessage()}");
    }

    public function tags(): array
    {
        return ['push-notification', 'notification:' . $this->notification->id, 'user:' . $this->notification->user_id];
    }
}
