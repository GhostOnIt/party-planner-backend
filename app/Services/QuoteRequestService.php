<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestActivity;
use App\Models\User;
use App\Notifications\NewQuoteRequestNotification;
use App\Notifications\QuoteRequestCallScheduledNotification;
use App\Notifications\QuoteRequestUpdatedNotification;

class QuoteRequestService
{
    public function generateTrackingCode(): string
    {
        return 'QR-' . strtoupper(substr((string) str()->uuid(), 0, 8));
    }

    public function logActivity(
        QuoteRequest $quoteRequest,
        string $type,
        ?string $message = null,
        ?array $metadata = null,
        ?string $userId = null
    ): QuoteRequestActivity {
        return QuoteRequestActivity::create([
            'quote_request_id' => $quoteRequest->id,
            'user_id' => $userId,
            'activity_type' => $type,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    public function notifyAdminsNewRequest(QuoteRequest $quoteRequest): void
    {
        $admins = User::where('role', UserRole::ADMIN->value)->get();
        foreach ($admins as $admin) {
            $admin->notify(new NewQuoteRequestNotification($quoteRequest));
        }
    }

    public function notifyCustomerUpdate(QuoteRequest $quoteRequest, string $title, string $message): void
    {
        if (!$quoteRequest->user) {
            return;
        }

        $quoteRequest->user->notify(new QuoteRequestUpdatedNotification($quoteRequest, $title, $message));
    }

    public function notifyCustomerCallScheduled(QuoteRequest $quoteRequest): void
    {
        if (!$quoteRequest->user || !$quoteRequest->call_scheduled_at) {
            return;
        }

        $quoteRequest->user->notify(new QuoteRequestCallScheduledNotification($quoteRequest));
    }
}
