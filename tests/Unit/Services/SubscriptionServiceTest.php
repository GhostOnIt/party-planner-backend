<?php

namespace Tests\Unit\Services;

use App\Enums\PlanType;
use App\Models\Event;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionService::class);
        $this->user = User::factory()->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */

    public function test_calculate_price_for_starter_with_no_extra_guests(): void
    {
        $result = $this->service->calculatePrice(PlanType::STARTER->value, 30);

        $this->assertSame(5000, $result['base_price']);
        $this->assertSame(0, $result['extra_guests']);
        $this->assertSame(5000, $result['total_price']);
    }

    public function test_calculate_price_for_starter_with_extra_guests(): void
    {
        // Starter = 50 included, 50 per extra. 80 guests → 30 extra × 50 = 1500.
        $result = $this->service->calculatePrice(PlanType::STARTER->value, 80);

        $this->assertSame(30, $result['extra_guests']);
        $this->assertSame(1500, $result['extra_guests_cost']);
        $this->assertSame(6500, $result['total_price']);
    }

    public function test_calculate_price_for_pro_plan(): void
    {
        $result = $this->service->calculatePrice(PlanType::PRO->value, 200);

        $this->assertSame(15000, $result['base_price']);
        $this->assertSame(0, $result['extra_guests']);
        $this->assertSame(15000, $result['total_price']);
    }

    public function test_calculate_price_throws_on_invalid_plan(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->calculatePrice('not_a_plan', 50);
    }

    /*
    |--------------------------------------------------------------------------
    | Creation, update, cancel
    |--------------------------------------------------------------------------
    */

    public function test_create_persists_subscription(): void
    {
        $event = Event::factory()->create(['user_id' => $this->user->id]);

        $subscription = $this->service->create($event, $this->user, PlanType::STARTER->value, 50);

        $this->assertSame($event->id, $subscription->event_id);
        $this->assertSame($this->user->id, $subscription->user_id);
        $this->assertSame(PlanType::STARTER->value, $subscription->plan_type);
        $this->assertSame('pending', $subscription->payment_status);
        $this->assertTrue($subscription->expires_at->isFuture());
    }

    public function test_update_recomputes_pricing_and_persists(): void
    {
        $event = Event::factory()->create(['user_id' => $this->user->id]);
        $subscription = $this->service->create($event, $this->user, PlanType::STARTER->value, 50);

        $updated = $this->service->update($subscription, PlanType::PRO->value, 250);

        $this->assertSame(PlanType::PRO->value, $updated->plan_type);
        $this->assertSame(250, $updated->guest_count);
    }

    public function test_cancel_marks_subscription_cancelled(): void
    {
        $event = Event::factory()->create(['user_id' => $this->user->id]);
        $subscription = $this->service->create($event, $this->user, PlanType::STARTER->value, 50);

        $cancelled = $this->service->cancel($subscription);

        $this->assertSame('cancelled', $cancelled->status);
    }

    public function test_renew_extends_expiration_and_sets_pending(): void
    {
        $event = Event::factory()->create(['user_id' => $this->user->id]);
        $subscription = $this->service->create($event, $this->user, PlanType::STARTER->value, 50);
        $subscription->update(['expires_at' => now()->subDay(), 'payment_status' => 'paid']);

        $renewed = $this->service->renew($subscription);

        $this->assertTrue($renewed->expires_at->isFuture());
        $this->assertSame('pending', $renewed->payment_status);
    }

    /*
    |--------------------------------------------------------------------------
    | Plan comparison & feature checks
    |--------------------------------------------------------------------------
    */

    public function test_get_plan_comparison_returns_all_plan_types(): void
    {
        $comparison = $this->service->getPlanComparison();

        $this->assertArrayHasKey(PlanType::STARTER->value, $comparison);
        $this->assertArrayHasKey(PlanType::PRO->value, $comparison);
        $this->assertSame('Starter', $comparison[PlanType::STARTER->value]['name']);
    }

    /*
    |--------------------------------------------------------------------------
    | Trial subscription
    |--------------------------------------------------------------------------
    */

    public function test_create_trial_subscription_returns_null_when_no_trial_plan_exists(): void
    {
        $result = $this->service->createTrialSubscription($this->user);

        $this->assertNull($result);
    }

    public function test_create_trial_subscription_creates_account_level_subscription(): void
    {
        $trialPlan = Plan::create([
            'name' => 'Essai gratuit',
            'slug' => 'trial',
            'price' => 0,
            'duration_days' => 14,
            'is_trial' => true,
            'is_active' => true,
            'sort_order' => 1,
            'limits' => ['guests.max_per_event' => 20],
            'features' => [],
        ]);

        $sub = $this->service->createTrialSubscription($this->user);

        $this->assertNotNull($sub);
        $this->assertSame($trialPlan->id, $sub->plan_id);
        $this->assertSame('trial', $sub->status);
        $this->assertSame('paid', $sub->payment_status);
        $this->assertNull($sub->event_id);
    }

    public function test_create_trial_subscription_returns_null_if_user_has_account_subscription(): void
    {
        Plan::create([
            'name' => 'Essai gratuit',
            'slug' => 'trial',
            'price' => 0,
            'duration_days' => 14,
            'is_trial' => true,
            'is_active' => true,
            'sort_order' => 1,
            'limits' => [],
            'features' => [],
        ]);

        Subscription::create([
            'user_id' => $this->user->id,
            'event_id' => null,
            'plan_type' => 'starter',
            'base_price' => 0,
            'guest_count' => 0,
            'guest_price_per_unit' => 0,
            'total_price' => 0,
            'payment_status' => 'paid',
            'status' => 'active',
        ]);

        $result = $this->service->createTrialSubscription($this->user);

        $this->assertNull($result);
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription lookups
    |--------------------------------------------------------------------------
    */

    public function test_get_user_active_subscription_returns_active_account_subscription(): void
    {
        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 15000,
            'duration_days' => 240,
            'is_trial' => false,
            'is_active' => true,
            'sort_order' => 2,
            'limits' => [],
            'features' => [],
        ]);

        Subscription::create([
            'user_id' => $this->user->id,
            'event_id' => null,
            'plan_id' => $plan->id,
            'plan_type' => 'pro',
            'base_price' => 15000,
            'guest_count' => 200,
            'guest_price_per_unit' => 0,
            'total_price' => 15000,
            'payment_status' => 'paid',
            'status' => 'active',
            'expires_at' => now()->addMonths(8),
        ]);

        $found = $this->service->getUserActiveSubscription($this->user);

        $this->assertNotNull($found);
        $this->assertSame('pro', $found->plan_type);
    }

    public function test_get_user_active_subscription_ignores_expired(): void
    {
        Subscription::create([
            'user_id' => $this->user->id,
            'event_id' => null,
            'plan_type' => 'starter',
            'base_price' => 5000,
            'guest_count' => 50,
            'guest_price_per_unit' => 0,
            'total_price' => 5000,
            'payment_status' => 'paid',
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        $this->assertNull($this->service->getUserActiveSubscription($this->user));
    }

    public function test_handle_expiration_marks_overdue_subscriptions_expired(): void
    {
        Subscription::create([
            'user_id' => $this->user->id,
            'event_id' => null,
            'plan_type' => 'starter',
            'base_price' => 5000,
            'guest_count' => 50,
            'guest_price_per_unit' => 0,
            'total_price' => 5000,
            'payment_status' => 'paid',
            'status' => 'active',
            'expires_at' => now()->subDays(2),
        ]);

        $count = $this->service->handleExpiration();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'status' => 'expired',
        ]);
    }

    public function test_create_subscription_with_plan_marks_complimentary_as_paid(): void
    {
        $freePlan = Plan::create([
            'name' => 'Gratuit',
            'slug' => 'free',
            'price' => 0,
            'duration_days' => 30,
            'is_trial' => false,
            'is_active' => true,
            'sort_order' => 0,
            'limits' => [],
            'features' => [],
        ]);

        $sub = $this->service->createSubscriptionWithPlan($this->user, $freePlan);

        $this->assertSame('paid', $sub->payment_status);
        $this->assertSame('active', $sub->status);
    }

    public function test_create_subscription_with_paid_plan_starts_pending(): void
    {
        $pro = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 15000,
            'duration_days' => 240,
            'is_trial' => false,
            'is_active' => true,
            'sort_order' => 2,
            'limits' => [],
            'features' => [],
        ]);

        $sub = $this->service->createSubscriptionWithPlan($this->user, $pro);

        $this->assertSame('pending', $sub->payment_status);
        $this->assertSame('pending', $sub->status);
    }

    public function test_upgrade_to_plan_switches_plan_and_extends_expiry(): void
    {
        $starter = Plan::create([
            'name' => 'Starter', 'slug' => 'starter', 'price' => 5000, 'duration_days' => 120,
            'is_trial' => false, 'is_active' => true, 'sort_order' => 1, 'limits' => [], 'features' => [],
        ]);
        $pro = Plan::create([
            'name' => 'Pro', 'slug' => 'pro', 'price' => 15000, 'duration_days' => 240,
            'is_trial' => false, 'is_active' => true, 'sort_order' => 2, 'limits' => [], 'features' => [],
        ]);

        $sub = $this->service->createSubscriptionWithPlan($this->user, $starter);
        $sub->update(['payment_status' => 'paid', 'status' => 'active']);

        $upgraded = $this->service->upgradeToPlan($sub->fresh(), $pro);

        $this->assertSame($pro->id, $upgraded->plan_id);
        $this->assertSame('pro', $upgraded->plan_type);
        // Paid → pending because there's a price difference to settle.
        $this->assertSame('pending', $upgraded->payment_status);
    }
}
