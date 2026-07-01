<?php

namespace App\Jobs;

use App\Mail\PaymentConfirmationMail;
use App\Models\Notification;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public Payment $payment
    ) {}

    public function handle(): void
    {
        if (!$this->payment->exists || !$this->payment->isCompleted()) {
            return;
        }

        $this->payment->load('subscription.user', 'subscription.event', 'subscription.plan');

        $user = $this->payment->subscription->user;
        $subscription = $this->payment->subscription;
        $serviceLabel = $subscription->event
            ? 'l’événement "' . $subscription->event->title . '"'
            : 'votre abonnement ' . $subscription->plan_label;

        // Send email
        if ($user->email) {
            Mail::to($user->email)->send(new PaymentConfirmationMail($this->payment));
            Log::info("SendPaymentConfirmationJob: Email sent to {$user->email}");
        }

        // Create in-app notification
        Notification::create([
            'user_id' => $user->id,
            'event_id' => $subscription->event_id,
            'type' => 'budget_alert',
            'title' => 'Paiement confirmé',
            'message' => "Votre paiement de {$this->payment->formatted_amount} pour {$serviceLabel} a été confirmé.",
            'sent_via' => 'email',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendPaymentConfirmationJob failed for payment {$this->payment->id}: {$exception->getMessage()}");
    }

    public function tags(): array
    {
        return ['payment-confirmation', 'payment:' . $this->payment->id];
    }
}
