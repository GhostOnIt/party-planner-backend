<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(['user_id' => $this->user->id]);
        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Index Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_list_their_payments(): void
    {
        Sanctum::actingAs($this->user);

        Payment::factory()->count(3)->create([
            'subscription_id' => $this->subscription->id,
        ]);

        // Create payments for another user
        $otherSubscription = Subscription::factory()->create();
        Payment::factory()->count(2)->create([
            'subscription_id' => $otherSubscription->id,
        ]);

        $response = $this->getJson('/api/payments');

        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_list_payments(): void
    {
        $response = $this->getJson('/api/payments');

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Initiate MTN Payment Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_initiate_mtn_payment(): void
    {
        Sanctum::actingAs($this->user);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('initiateMtnPayment')
                ->once()
                ->andReturn([
                    'success' => true,
                    'payment' => Payment::factory()->pending()->mtn()->create([
                        'subscription_id' => $this->subscription->id,
                    ]),
                    'reference' => 'MTN-123456',
                ]);
        });

        $response = $this->postJson('/api/payments/mtn/initiate', [
            'subscription_id' => $this->subscription->id,
            'phone' => '670000000',
        ]);

        $response->assertOk();
    }

    public function test_user_cannot_initiate_payment_for_other_user_subscription(): void
    {
        Sanctum::actingAs($this->user);

        $otherSubscription = Subscription::factory()->create();

        $response = $this->postJson('/api/payments/mtn/initiate', [
            'subscription_id' => $otherSubscription->id,
            'phone' => '670000000',
        ]);

        $response->assertForbidden();
    }

    public function test_initiate_payment_requires_phone(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/payments/mtn/initiate', [
            'subscription_id' => $this->subscription->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    /*
    |--------------------------------------------------------------------------
    | Initiate Airtel Payment Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_initiate_airtel_payment(): void
    {
        Sanctum::actingAs($this->user);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('initiateAirtelPayment')
                ->once()
                ->andReturn([
                    'success' => true,
                    'payment' => Payment::factory()->pending()->airtel()->create([
                        'subscription_id' => $this->subscription->id,
                    ]),
                    'reference' => 'AIRTEL-123456',
                ]);
        });

        $response = $this->postJson('/api/payments/airtel/initiate', [
            'subscription_id' => $this->subscription->id,
            'phone' => '690000000',
        ]);

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Status Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_check_payment_status(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->pending()->create([
            'subscription_id' => $this->subscription->id,
        ]);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('checkStatus')
                ->once()
                ->andReturn(['status' => 'pending', 'message' => 'En attente']);
        });

        $response = $this->getJson("/api/payments/{$payment->id}/status");

        $response->assertOk();
    }

    public function test_user_cannot_check_other_user_payment_status(): void
    {
        Sanctum::actingAs($this->user);

        $otherSubscription = Subscription::factory()->create();
        $payment = Payment::factory()->create([
            'subscription_id' => $otherSubscription->id,
        ]);

        $response = $this->getJson("/api/payments/{$payment->id}/status");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Poll Payment Status Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_poll_payment_status(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->pending()->create([
            'subscription_id' => $this->subscription->id,
        ]);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('checkStatus')
                ->once()
                ->andReturn(['status' => 'pending']);
        });

        $response = $this->getJson("/api/payments/{$payment->id}/poll");

        $response->assertOk();
    }

    public function test_user_cannot_poll_other_user_payment(): void
    {
        Sanctum::actingAs($this->user);

        $otherSubscription = Subscription::factory()->create();
        $payment = Payment::factory()->create([
            'subscription_id' => $otherSubscription->id,
        ]);

        $response = $this->getJson("/api/payments/{$payment->id}/poll");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Retry Payment Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_retry_failed_payment(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->failed()->create([
            'subscription_id' => $this->subscription->id,
        ]);

        $response = $this->postJson("/api/payments/{$payment->id}/retry");

        $response->assertRedirect();
    }

    public function test_user_cannot_retry_non_failed_payment(): void
    {
        Sanctum::actingAs($this->user);

        $payment = Payment::factory()->completed()->create([
            'subscription_id' => $this->subscription->id,
        ]);

        $response = $this->postJson("/api/payments/{$payment->id}/retry");

        $response->assertRedirect();
    }

    public function test_user_cannot_retry_other_user_payment(): void
    {
        Sanctum::actingAs($this->user);

        $otherSubscription = Subscription::factory()->create();
        $payment = Payment::factory()->failed()->create([
            'subscription_id' => $otherSubscription->id,
        ]);

        $response = $this->postJson("/api/payments/{$payment->id}/retry");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_all_payments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        Payment::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/payments');

        $response->assertOk();
    }

    public function test_regular_user_cannot_access_admin_payments(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/admin/payments');

        $response->assertForbidden();
    }
}
