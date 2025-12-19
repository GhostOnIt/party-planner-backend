<?php

namespace App\Jobs;

use App\Mail\NotificationMail;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailNotificationJob implements ShouldQueue
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

        if (!$this->notification->user->email) {
            Log::warning("SendEmailNotificationJob: User {$this->notification->user_id} has no email");
            return;
        }

        Mail::to($this->notification->user->email)
            ->send(new NotificationMail($this->notification));

        Log::info("SendEmailNotificationJob: Email sent for notification {$this->notification->id}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendEmailNotificationJob failed for notification {$this->notification->id}: {$exception->getMessage()}");
    }

    public function tags(): array
    {
        return ['email-notification', 'notification:' . $this->notification->id, 'user:' . $this->notification->user_id];
    }
}
