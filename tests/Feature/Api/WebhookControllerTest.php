<?php

namespace Tests\Feature\Api;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | MTN Mobile Money Webhook Tests
    |--------------------------------------------------------------------------
    */

    public function test_mtn_webhook_processes_successful_payment(): void
    {
        $subscription = Subscription::factory()->pending()->create();
        $payment = Payment::factory()->pending()->mtn()->create([
            'subscription_id' => $subscription->id,
            'transaction_reference' => 'MTN-REF-123',
        ]);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleMtnCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/webhooks/mtn', [
            'referenceId' => 'MTN-REF-123',
            'status' => 'SUCCESSFUL',
            'financialTransactionId' => 'FT-12345',
            'amount' => '10000',
            'currency' => 'XAF',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_mtn_webhook_handles_failed_payment(): void
    {
        $subscription = Subscription::factory()->pending()->create();
        $payment = Payment::factory()->pending()->mtn()->create([
            'subscription_id' => $subscription->id,
            'transaction_reference' => 'MTN-REF-456',
        ]);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleMtnCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/webhooks/mtn', [
            'referenceId' => 'MTN-REF-456',
            'status' => 'FAILED',
            'reason' => 'INSUFFICIENT_FUNDS',
        ]);

        $response->assertOk();
    }

    public function test_mtn_webhook_rejects_invalid_signature(): void
    {
        Config::set('partyplanner.payments.mtn_mobile_money.webhook_secret', 'test-secret');

        $response = $this->postJson('/webhooks/mtn', [
            'referenceId' => 'MTN-REF-789',
            'status' => 'SUCCESSFUL',
        ], [
            'X-Callback-Signature' => 'invalid-signature',
        ]);

        $response->assertUnauthorized()
            ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_mtn_webhook_accepts_valid_signature(): void
    {
        $secret = 'test-secret';
        Config::set('partyplanner.payments.mtn_mobile_money.webhook_secret', $secret);

        $payload = json_encode([
            'referenceId' => 'MTN-REF-999',
            'status' => 'SUCCESSFUL',
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleMtnCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/webhooks/mtn', json_decode($payload, true), [
            'X-Callback-Signature' => $signature,
        ]);

        $response->assertOk();
    }

    public function test_mtn_webhook_skips_validation_in_dev_mode(): void
    {
        Config::set('partyplanner.payments.mtn_mobile_money.webhook_secret', null);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleMtnCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/webhooks/mtn', [
            'referenceId' => 'MTN-REF-DEV',
            'status' => 'SUCCESSFUL',
        ]);

        $response->assertOk();
    }

    public function test_mtn_webhook_handles_processing_error(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleMtnCallback')
                ->once()
                ->andThrow(new \Exception('Processing failed'));
        });

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $response = $this->postJson('/webhooks/mtn', [
            'referenceId' => 'MTN-REF-ERROR',
            'status' => 'SUCCESSFUL',
        ]);

        $response->assertStatus(500)
            ->assertJson(['error' => 'Processing error']);
    }

    /*
    |--------------------------------------------------------------------------
    | Airtel Money Webhook Tests
    |--------------------------------------------------------------------------
    */

    public function test_airtel_webhook_processes_successful_payment(): void
    {
        $subscription = Subscription::factory()->pending()->create();
        $payment = Payment::factory()->pending()->airtel()->create([
            'subscription_id' => $subscription->id,
            'transaction_reference' => 'AIRTEL-REF-123',
        ]);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleAirtelCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/webhooks/airtel', [
            'transaction' => [
                'id' => 'AIRTEL-REF-123',
                'status' => 'TS',
                'message' => 'Transaction successful',
            ],
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_airtel_webhook_handles_failed_payment(): void
    {
        $subscription = Subscription::factory()->pending()->create();
        $payment = Payment::factory()->pending()->airtel()->create([
            'subscription_id' => $subscription->id,
        ]);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleAirtelCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/webhooks/airtel', [
            'transaction' => [
                'id' => 'AIRTEL-REF-456',
                'status' => 'TF',
                'message' => 'Transaction failed',
            ],
        ]);

        $response->assertOk();
    }

    public function test_airtel_webhook_rejects_invalid_signature(): void
    {
        Config::set('partyplanner.payments.airtel_money.webhook_secret', 'airtel-secret');

        $response = $this->postJson('/webhooks/airtel', [
            'transaction' => [
                'id' => 'AIRTEL-REF-789',
                'status' => 'TS',
            ],
        ], [
            'X-Airtel-Signature' => 'invalid-signature',
        ]);

        $response->assertUnauthorized()
            ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_airtel_webhook_accepts_valid_signature(): void
    {
        $secret = 'airtel-secret';
        Config::set('partyplanner.payments.airtel_money.webhook_secret', $secret);

        $payload = json_encode([
            'transaction' => [
                'id' => 'AIRTEL-REF-999',
                'status' => 'TS',
            ],
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleAirtelCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/webhooks/airtel', json_decode($payload, true), [
            'X-Airtel-Signature' => $signature,
        ]);

        $response->assertOk();
    }

    public function test_airtel_webhook_handles_processing_error(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleAirtelCallback')
                ->once()
                ->andThrow(new \Exception('Airtel processing failed'));
        });

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $response = $this->postJson('/webhooks/airtel', [
            'transaction' => [
                'id' => 'AIRTEL-REF-ERROR',
                'status' => 'TS',
            ],
        ]);

        $response->assertStatus(500)
            ->assertJson(['error' => 'Processing error']);
    }

    /*
    |--------------------------------------------------------------------------
    | Legacy Callback Routes Tests
    |--------------------------------------------------------------------------
    */

    public function test_legacy_mtn_callback_route_works(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleMtnCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/payments/mtn/callback', [
            'referenceId' => 'LEGACY-MTN-123',
            'status' => 'SUCCESSFUL',
        ]);

        $response->assertOk();
    }

    public function test_legacy_airtel_callback_route_works(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleAirtelCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/payments/airtel/callback', [
            'transaction' => [
                'id' => 'LEGACY-AIRTEL-123',
                'status' => 'TS',
            ],
        ]);

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook Logging Tests
    |--------------------------------------------------------------------------
    */

    public function test_mtn_webhook_logs_incoming_request(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'MTN webhook received');
            });

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleMtnCallback')
                ->once()
                ->andReturn(true);
        });

        $this->postJson('/webhooks/mtn', [
            'referenceId' => 'LOG-TEST-123',
            'status' => 'SUCCESSFUL',
        ]);
    }

    public function test_airtel_webhook_logs_incoming_request(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Airtel webhook received');
            });

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleAirtelCallback')
                ->once()
                ->andReturn(true);
        });

        $this->postJson('/webhooks/airtel', [
            'transaction' => [
                'id' => 'LOG-TEST-456',
                'status' => 'TS',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Edge Cases Tests
    |--------------------------------------------------------------------------
    */

    public function test_mtn_webhook_handles_empty_payload(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleMtnCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/webhooks/mtn', []);

        $response->assertOk();
    }

    public function test_airtel_webhook_handles_empty_payload(): void
    {
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleAirtelCallback')
                ->once()
                ->andReturn(true);
        });

        $response = $this->postJson('/webhooks/airtel', []);

        $response->assertOk();
    }

    public function test_mtn_webhook_handles_malformed_json(): void
    {
        $response = $this->call('POST', '/webhooks/mtn', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json {');

        $response->assertStatus(500);
    }
}
