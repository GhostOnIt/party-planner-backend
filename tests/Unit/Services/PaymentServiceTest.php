<?php

namespace Tests\Unit\Services;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $service;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentService();

        $user = User::factory()->create();
        $plan = Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 5000,
            'duration_days' => 120,
            'is_trial' => false,
            'is_active' => true,
            'sort_order' => 1,
            'limits' => [],
            'features' => [],
        ]);

        $this->subscription = Subscription::create([
            'user_id' => $user->id,
            'event_id' => null,
            'plan_id' => $plan->id,
            'plan_type' => 'starter',
            'base_price' => 5000,
            'guest_count' => 50,
            'guest_price_per_unit' => 0,
            'total_price' => 5000,
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        // Force simulate mode regardless of env (config matches docker-compose).
        config(['partyplanner.payments.mtn_mobile_money.enabled' => true]);
        config(['partyplanner.payments.mtn_mobile_money.simulate' => true]);
        config(['partyplanner.payments.mtn_mobile_money.environment' => 'sandbox']);
    }

    /*
    |--------------------------------------------------------------------------
    | MTN initiation (simulation mode)
    |--------------------------------------------------------------------------
    */

    public function test_initiate_mtn_payment_creates_pending_payment_in_simulate_mode(): void
    {
        $result = $this->service->initiateMtnPayment($this->subscription, '067123450');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $this->subscription->id,
            'payment_method' => 'mtn_mobile_money',
        ]);
    }

    public function test_simulated_mtn_payment_succeeds_when_phone_ends_in_zero(): void
    {
        $result = $this->service->initiateMtnPayment($this->subscription, '067123450');

        $payment = $result['payment'];
        $this->assertSame('completed', $payment->status);
    }

    public function test_simulated_mtn_payment_fails_when_phone_ends_in_one(): void
    {
        $result = $this->service->initiateMtnPayment($this->subscription, '067123451');

        $payment = $result['payment'];
        $this->assertSame('failed', $payment->status);
    }

    public function test_initiate_mtn_payment_fails_when_provider_disabled(): void
    {
        config(['partyplanner.payments.mtn_mobile_money.enabled' => false]);

        $result = $this->service->initiateMtnPayment($this->subscription, '067123450');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('MTN', $result['message']);
    }

    public function test_initiate_mtn_payment_rejects_invalid_phone_outside_sandbox(): void
    {
        // Switch to production environment so phone validation actually runs.
        config(['partyplanner.payments.mtn_mobile_money.environment' => 'production']);

        $result = $this->service->initiateMtnPayment($this->subscription, '04INVALID');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('06', $result['message']);
    }

    /*
    |--------------------------------------------------------------------------
    | Callbacks
    |--------------------------------------------------------------------------
    */

    public function test_handle_mtn_callback_dispatches_job(): void
    {
        Bus::fake();

        $ok = $this->service->handleMtnCallback(['externalId' => 'X', 'status' => 'SUCCESSFUL']);

        $this->assertTrue($ok);
        Bus::assertDispatched(\App\Jobs\ProcessPaymentCallbackJob::class);
    }

    public function test_process_mtn_callback_marks_payment_completed_on_success(): void
    {
        $payment = Payment::create([
            'subscription_id' => $this->subscription->id,
            'amount' => 5000,
            'currency' => 'XAF',
            'payment_method' => 'mtn_mobile_money',
            'status' => 'pending',
            'transaction_reference' => 'EXT-123',
            'metadata' => [],
        ]);

        $this->service->processMtnCallback([
            'externalId' => 'EXT-123',
            'status' => 'SUCCESSFUL',
            'financialTransactionId' => 'TXN-456',
        ]);

        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_process_mtn_callback_marks_payment_failed_on_failed_status(): void
    {
        $payment = Payment::create([
            'subscription_id' => $this->subscription->id,
            'amount' => 5000,
            'currency' => 'XAF',
            'payment_method' => 'mtn_mobile_money',
            'status' => 'pending',
            'transaction_reference' => 'EXT-FAIL',
            'metadata' => [],
        ]);

        $this->service->processMtnCallback([
            'externalId' => 'EXT-FAIL',
            'status' => 'FAILED',
            'reason' => 'INSUFFICIENT_FUNDS',
        ]);

        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_process_mtn_callback_no_op_when_external_id_missing(): void
    {
        // Should not throw — just log and return.
        $this->service->processMtnCallback([]);
        $this->assertTrue(true);
    }

    public function test_process_mtn_callback_no_op_when_payment_not_found(): void
    {
        $this->service->processMtnCallback([
            'externalId' => 'DOES_NOT_EXIST',
            'status' => 'SUCCESSFUL',
        ]);
        $this->assertTrue(true);
    }

    public function test_process_airtel_callback_marks_payment_completed(): void
    {
        $payment = Payment::create([
            'subscription_id' => $this->subscription->id,
            'amount' => 5000,
            'currency' => 'XAF',
            'payment_method' => 'airtel_money',
            'status' => 'pending',
            'transaction_reference' => 'AIR-1',
            'metadata' => [],
        ]);

        $this->service->processAirtelCallback([
            'transaction' => [
                'id' => 'AIR-1',
                'status' => 'TS',
                'airtel_money_id' => 'AM-99',
            ],
        ]);

        $this->assertSame('completed', $payment->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Status check
    |--------------------------------------------------------------------------
    */

    public function test_check_status_returns_completed_for_completed_payment(): void
    {
        $payment = Payment::create([
            'subscription_id' => $this->subscription->id,
            'amount' => 5000,
            'currency' => 'XAF',
            'payment_method' => 'mtn_mobile_money',
            'status' => 'completed',
            'metadata' => [],
        ]);

        $status = $this->service->checkStatus($payment);

        $this->assertSame('completed', $status['status']);
    }

    public function test_check_status_returns_failed_for_failed_payment(): void
    {
        $payment = Payment::create([
            'subscription_id' => $this->subscription->id,
            'amount' => 5000,
            'currency' => 'XAF',
            'payment_method' => 'mtn_mobile_money',
            'status' => 'failed',
            'metadata' => ['provider_reason' => 'INSUFFICIENT_FUNDS'],
        ]);

        $status = $this->service->checkStatus($payment);

        $this->assertSame('failed', $status['status']);
    }
}
