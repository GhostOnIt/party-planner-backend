<?php

namespace App\Services;

use App\Jobs\SendPaymentConfirmationJob;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create or retrieve a Stripe customer.
     */
    public function getOrCreateCustomer(User $user): Customer
    {
        if ($user->stripe_customer_id) {
            try {
                return $this->stripe->customers->retrieve($user->stripe_customer_id);
            } catch (ApiErrorException $e) {
                Log::warning('Stripe customer not found, creating new one', [
                    'user_id' => $user->id,
                    'stripe_customer_id' => $user->stripe_customer_id,
                ]);
            }
        }

        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Create a Stripe Checkout session for one-time payment.
     */
    public function createCheckoutSession(
        Subscription $subscription,
        string $successUrl,
        string $cancelUrl
    ): Session {
        $user = $subscription->event->user;
        $customer = $this->getOrCreateCustomer($user);

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'amount' => $subscription->total_price,
            'currency' => config('partyplanner.currency.code', 'XAF'),
            'payment_method' => 'stripe',
            'status' => 'pending',
            'metadata' => [
                'subscription_id' => $subscription->id,
                'event_id' => $subscription->event_id,
                'plan_type' => $subscription->plan_type,
            ],
        ]);

        $session = $this->stripe->checkout->sessions->create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower(config('partyplanner.currency.code', 'XAF')),
                    'product_data' => [
                        'name' => "Party Planner - {$subscription->plan_type}",
                        'description' => "Subscription for {$subscription->event->title}",
                    ],
                    'unit_amount' => (int) ($subscription->total_price * 100), // Stripe uses cents
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
            ],
            'expires_at' => now()->addHours(2)->timestamp,
        ]);

        $payment->update([
            'transaction_reference' => $session->id,
            'metadata' => array_merge($payment->metadata ?? [], [
                'checkout_session_id' => $session->id,
                'checkout_url' => $session->url,
            ]),
        ]);

        return $session;
    }

    /**
     * Create a recurring subscription checkout session.
     */
    public function createSubscriptionCheckout(
        User $user,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): Session {
        $customer = $this->getOrCreateCustomer($user);

        return $this->stripe->checkout->sessions->create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
            'subscription_data' => [
                'metadata' => $metadata,
            ],
        ]);
    }

    /**
     * Create a Stripe Price for recurring billing.
     */
    public function createPrice(
        int $amount,
        string $currency,
        string $interval = 'month',
        string $productName = 'Party Planner Subscription'
    ): Price {
        return $this->stripe->prices->create([
            'unit_amount' => $amount * 100, // Convert to cents
            'currency' => strtolower($currency),
            'recurring' => ['interval' => $interval],
            'product_data' => [
                'name' => $productName,
            ],
        ]);
    }

    /**
     * Cancel a Stripe subscription.
     */
    public function cancelSubscription(string $subscriptionId, bool $immediately = false): StripeSubscription
    {
        if ($immediately) {
            return $this->stripe->subscriptions->cancel($subscriptionId);
        }

        return $this->stripe->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resumeSubscription(string $subscriptionId): StripeSubscription
    {
        return $this->stripe->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => false,
        ]);
    }

    /**
     * Handle Stripe webhook events.
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook: Invalid payload', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Invalid payload'];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Stripe webhook: Invalid signature', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        Log::info('Stripe webhook received', ['type' => $event->type]);

        return match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'customer.subscription.created' => $this->handleSubscriptionCreated($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            default => ['success' => true, 'message' => 'Event type not handled'],
        };
    }

    /**
     * Handle checkout session completed.
     */
    protected function handleCheckoutCompleted(object $session): array
    {
        $paymentId = $session->metadata->payment_id ?? null;

        if (!$paymentId) {
            Log::warning('Stripe checkout: Missing payment_id in metadata', [
                'session_id' => $session->id,
            ]);
            return ['success' => false, 'message' => 'Missing payment_id'];
        }

        $payment = Payment::find($paymentId);

        if (!$payment) {
            Log::warning('Stripe checkout: Payment not found', ['payment_id' => $paymentId]);
            return ['success' => false, 'message' => 'Payment not found'];
        }

        if ($session->payment_status === 'paid') {
            $payment->markAsCompleted($session->payment_intent);
            SendPaymentConfirmationJob::dispatch($payment);
            Log::info('Stripe payment completed', ['payment_id' => $payment->id]);
        }

        return ['success' => true, 'message' => 'Checkout completed'];
    }

    /**
     * Handle payment intent succeeded.
     */
    protected function handlePaymentSucceeded(object $paymentIntent): array
    {
        Log::info('Stripe payment intent succeeded', ['id' => $paymentIntent->id]);
        return ['success' => true, 'message' => 'Payment succeeded'];
    }

    /**
     * Handle payment intent failed.
     */
    protected function handlePaymentFailed(object $paymentIntent): array
    {
        Log::warning('Stripe payment intent failed', [
            'id' => $paymentIntent->id,
            'error' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
        ]);
        return ['success' => true, 'message' => 'Payment failed event processed'];
    }

    /**
     * Handle subscription created.
     */
    protected function handleSubscriptionCreated(object $subscription): array
    {
        Log::info('Stripe subscription created', [
            'id' => $subscription->id,
            'customer' => $subscription->customer,
        ]);
        return ['success' => true, 'message' => 'Subscription created'];
    }

    /**
     * Handle subscription updated.
     */
    protected function handleSubscriptionUpdated(object $subscription): array
    {
        Log::info('Stripe subscription updated', [
            'id' => $subscription->id,
            'status' => $subscription->status,
        ]);
        return ['success' => true, 'message' => 'Subscription updated'];
    }

    /**
     * Handle subscription deleted.
     */
    protected function handleSubscriptionDeleted(object $subscription): array
    {
        Log::info('Stripe subscription deleted', ['id' => $subscription->id]);
        return ['success' => true, 'message' => 'Subscription deleted'];
    }

    /**
     * Handle invoice payment succeeded.
     */
    protected function handleInvoicePaymentSucceeded(object $invoice): array
    {
        Log::info('Stripe invoice payment succeeded', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription,
        ]);
        return ['success' => true, 'message' => 'Invoice payment succeeded'];
    }

    /**
     * Handle invoice payment failed.
     */
    protected function handleInvoicePaymentFailed(object $invoice): array
    {
        Log::warning('Stripe invoice payment failed', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription,
        ]);
        return ['success' => true, 'message' => 'Invoice payment failed event processed'];
    }

    /**
     * Retrieve a checkout session.
     */
    public function retrieveCheckoutSession(string $sessionId): Session
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }

    /**
     * List customer's payment methods.
     */
    public function listPaymentMethods(string $customerId): array
    {
        $paymentMethods = $this->stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
        ]);

        return $paymentMethods->data;
    }

    /**
     * Create a refund.
     */
    public function refund(string $paymentIntentId, ?int $amount = null): \Stripe\Refund
    {
        $params = ['payment_intent' => $paymentIntentId];

        if ($amount) {
            $params['amount'] = $amount * 100; // Convert to cents
        }

        return $this->stripe->refunds->create($params);
    }

    /**
     * Get Stripe publishable key.
     */
    public function getPublishableKey(): string
    {
        return config('services.stripe.key');
    }
}
