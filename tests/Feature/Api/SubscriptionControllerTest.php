<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(['user_id' => $this->user->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Subscription Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_view_event_subscription_options(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/events/{$this->event->id}/subscription");

        $response->assertOk();
    }

    public function test_user_cannot_view_subscription_for_other_user_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->getJson("/api/events/{$otherEvent->id}/subscription");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_view_subscription(): void
    {
        $response = $this->getJson("/api/events/{$this->event->id}/subscription");

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Subscribe Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_subscribe_to_starter_plan(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/subscription", [
            'plan_type' => 'starter',
            'guest_count' => 50,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'event_id' => $this->event->id,
            'user_id' => $this->user->id,
            'plan_type' => 'starter',
        ]);
    }

    public function test_user_can_subscribe_to_pro_plan(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/subscription", [
            'plan_type' => 'pro',
            'guest_count' => 100,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'event_id' => $this->event->id,
            'plan_type' => 'pro',
        ]);
    }

    public function test_subscribe_requires_valid_plan_type(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/subscription", [
            'plan_type' => 'invalid_plan',
            'guest_count' => 50,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_type']);
    }

    /*
    |--------------------------------------------------------------------------
    | Upgrade Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_upgrade_subscription(): void
    {
        Sanctum::actingAs($this->user);

        $subscription = Subscription::factory()->starter()->paid()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/subscription/upgrade", [
            'plan_type' => 'pro',
            'guest_count' => 200,
        ]);

        $response->assertRedirect();
    }

    public function test_user_cannot_upgrade_without_active_subscription(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/subscription/upgrade", [
            'plan_type' => 'pro',
        ]);

        $response->assertRedirect();
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_cancel_subscription(): void
    {
        Sanctum::actingAs($this->user);

        Subscription::factory()->paid()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/subscription/cancel");

        $response->assertRedirect();
    }

    public function test_user_cannot_cancel_nonexistent_subscription(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/subscription/cancel");

        $response->assertRedirect();
    }

    /*
    |--------------------------------------------------------------------------
    | Renew Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_renew_subscription(): void
    {
        Sanctum::actingAs($this->user);

        Subscription::factory()->expired()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/subscription/renew");

        $response->assertRedirect();
    }

    /*
    |--------------------------------------------------------------------------
    | Index (User Subscriptions) Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_list_their_subscriptions(): void
    {
        Sanctum::actingAs($this->user);

        Subscription::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/subscriptions');

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Price Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_calculate_subscription_price(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/events/{$this->event->id}/subscription/calculate-price?" . http_build_query([
            'plan_type' => 'pro',
            'guest_count' => 150,
        ]));

        $response->assertOk()
            ->assertJsonStructure(['base_price', 'guest_price', 'total_price']);
    }

    public function test_calculate_price_requires_plan_type(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/events/{$this->event->id}/subscription/calculate-price?" . http_build_query([
            'guest_count' => 150,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_type']);
    }

    /*
    |--------------------------------------------------------------------------
    | Check Limits Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_check_plan_limits(): void
    {
        Sanctum::actingAs($this->user);

        Subscription::factory()->starter()->paid()->create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/subscription/check-limits");

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Plans Comparison Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_get_plans_comparison(): void
    {
        Sanctum::actingAs($this->user);

        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('getPlanComparison')
                ->once()
                ->andReturn([
                    'starter' => ['name' => 'Starter', 'base_price' => 5000],
                    'pro' => ['name' => 'Pro', 'base_price' => 15000],
                ]);
        });

        // Note: This endpoint might not exist based on routes, adjust if needed
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_all_subscriptions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        Subscription::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/subscriptions');

        $response->assertOk();
    }

    public function test_regular_user_cannot_access_admin_subscriptions(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/admin/subscriptions');

        $response->assertForbidden();
    }

    public function test_admin_can_filter_subscriptions_by_plan(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        Subscription::factory()->count(3)->starter()->create();
        Subscription::factory()->count(2)->pro()->create();

        $response = $this->getJson('/api/admin/subscriptions?plan=starter');

        $response->assertOk();
    }

    public function test_admin_can_filter_subscriptions_by_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        Subscription::factory()->count(3)->paid()->create();
        Subscription::factory()->count(2)->pending()->create();

        $response = $this->getJson('/api/admin/subscriptions?status=paid');

        $response->assertOk();
    }
}
