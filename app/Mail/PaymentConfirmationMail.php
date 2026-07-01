<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use \App\Mail\Concerns\EmbedsMailLogo;

    public function __construct(
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de paiement - Party Planner',
        );
    }

    public function content(): Content
    {
        $this->payment->loadMissing('subscription.user', 'subscription.event', 'subscription.plan');

        return new Content(
            markdown: 'emails.payment-confirmation',
            with: [
                'payment' => $this->payment,
                'subscription' => $this->payment->subscription,
                'event' => $this->payment->subscription->event,
                'user' => $this->payment->subscription->user,
                'actionUrl' => $this->payment->subscription->event
                    ? config('app.frontend_url', config('app.url')) . '/events/' . $this->payment->subscription->event->id
                    : config('app.frontend_url', config('app.url')) . '/subscriptions',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
