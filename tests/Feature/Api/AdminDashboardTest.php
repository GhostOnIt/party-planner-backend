<?php

namespace Tests\Feature\Api;

use App\Enums\EventType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlanType;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->user = User::factory()->create(['role' => UserRole::USER]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Stats Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_get_stats(): void
    {
        Sanctum::actingAs($this->admin);

        // Create some data
        User::factory()->count(5)->create();
        Event::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/admin/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'users',
                    'events',
                    'revenue',
                    'subscriptions',
                ],
            ]);
    }

    /**
     * @group postgres
     */
    public function test_admin_can_get_chart_data(): void
    {
        // Skip on SQLite - uses PostgreSQL-specific TO_CHAR function
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('This test requires PostgreSQL.');
        }

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/chart-data?period=month');

        $response->assertOk()
            ->assertJsonStructure([
                'chart_data',
                'period',
            ]);
    }

    /**
     * @group postgres
     */
    public function test_admin_can_get_chart_data_with_different_periods(): void
    {
        // Skip on SQLite - uses PostgreSQL-specific TO_CHAR function
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('This test requires PostgreSQL.');
        }

        Sanctum::actingAs($this->admin);

        foreach (['week', 'month', 'year'] as $period) {
            $response = $this->getJson("/api/admin/chart-data?period={$period}");
            $response->assertOk()
                ->assertJsonPath('period', $period);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Users Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_all_users(): void
    {
        Sanctum::actingAs($this->admin);

        User::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'events_count', 'collaborations_count'],
                ],
            ]);
    }

    public function test_admin_can_search_users(): void
    {
        Sanctum::actingAs($this->admin);

        User::factory()->create(['name' => 'Jean Dupont', 'email' => 'jean@test.com']);
        User::factory()->create(['name' => 'Marie Martin', 'email' => 'marie@test.com']);

        $response = $this->getJson('/api/admin/users?search=Jean');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        Sanctum::actingAs($this->admin);

        User::factory()->count(3)->create(['role' => UserRole::USER]);
        User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->getJson('/api/admin/users?role=user');

        $response->assertOk();
        // Should only get regular users (+ the initial $this->user)
        foreach ($response->json('data') as $userData) {
            $this->assertEquals('user', $userData['role']);
        }
    }

    public function test_admin_can_view_user_details(): void
    {
        Sanctum::actingAs($this->admin);

        $targetUser = User::factory()->create();
        Event::factory()->count(3)->create(['user_id' => $targetUser->id]);

        $response = $this->getJson("/api/admin/users/{$targetUser->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'stats',
            ]);
    }

    public function test_admin_can_update_user_role(): void
    {
        Sanctum::actingAs($this->admin);

        $targetUser = User::factory()->create(['role' => UserRole::USER]);

        $response = $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role' => UserRole::ADMIN->value,
        ]);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/admin/users/{$this->admin->id}/role", [
            'role' => 'user',
        ]);

        $response->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Vous ne pouvez pas modifier votre propre rôle.']);
    }

    public function test_admin_can_delete_user(): void
    {
        Sanctum::actingAs($this->admin);

        $targetUser = User::factory()->create(['role' => UserRole::USER]);

        $response = $this->deleteJson("/api/admin/users/{$targetUser->id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Utilisateur supprimé.']);

        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->deleteJson("/api/admin/users/{$this->admin->id}");

        $response->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Vous ne pouvez pas supprimer votre propre compte.']);
    }

    public function test_admin_cannot_delete_other_admin(): void
    {
        Sanctum::actingAs($this->admin);

        $otherAdmin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->deleteJson("/api/admin/users/{$otherAdmin->id}");

        $response->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Impossible de supprimer un autre administrateur.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Events Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_all_events(): void
    {
        Sanctum::actingAs($this->admin);

        Event::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/events');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'type', 'status', 'user', 'guests_count', 'tasks_count', 'budget_items_count'],
                ],
            ]);
    }

    public function test_admin_can_search_events(): void
    {
        Sanctum::actingAs($this->admin);

        Event::factory()->create(['title' => 'Mariage de Jean', 'user_id' => $this->user->id]);
        Event::factory()->create(['title' => 'Anniversaire de Marie', 'user_id' => $this->user->id]);

        $response = $this->getJson('/api/admin/events?search=Mariage');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_filter_events_by_type(): void
    {
        Sanctum::actingAs($this->admin);

        Event::factory()->create(['type' => EventType::MARIAGE, 'user_id' => $this->user->id]);
        Event::factory()->create(['type' => EventType::ANNIVERSAIRE, 'user_id' => $this->user->id]);

        $response = $this->getJson('/api/admin/events?type=mariage');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Payments Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_all_payments(): void
    {
        Sanctum::actingAs($this->admin);

        $event = Event::factory()->create(['user_id' => $this->user->id]);
        $subscription = Subscription::factory()->pending()->create(['event_id' => $event->id]);
        Payment::factory()->count(3)->create([
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->getJson('/api/admin/payments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'amount', 'payment_method', 'status'],
                ],
            ]);
    }

    public function test_admin_can_filter_payments_by_status(): void
    {
        Sanctum::actingAs($this->admin);

        $event = Event::factory()->create(['user_id' => $this->user->id]);
        $subscription = Subscription::factory()->pending()->create(['event_id' => $event->id]);

        Payment::factory()->completed()->create([
            'subscription_id' => $subscription->id,
        ]);
        Payment::factory()->pending()->create([
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->getJson('/api/admin/payments?status=completed');

        $response->assertOk();
        foreach ($response->json('data') as $payment) {
            $this->assertEquals('completed', $payment['status']);
        }
    }

    public function test_admin_can_filter_payments_by_method(): void
    {
        Sanctum::actingAs($this->admin);

        $event = Event::factory()->create(['user_id' => $this->user->id]);
        $subscription = Subscription::factory()->pending()->create(['event_id' => $event->id]);

        Payment::factory()->mtn()->create([
            'subscription_id' => $subscription->id,
        ]);
        Payment::factory()->airtel()->create([
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->getJson('/api/admin/payments?method=mtn_mobile_money');

        $response->assertOk();
        foreach ($response->json('data') as $payment) {
            $this->assertEquals('mtn_mobile_money', $payment['payment_method']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Subscriptions Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_all_subscriptions(): void
    {
        Sanctum::actingAs($this->admin);

        $event = Event::factory()->create(['user_id' => $this->user->id]);
        Subscription::factory()->pending()->count(3)->create(['event_id' => $event->id]);

        $response = $this->getJson('/api/admin/subscriptions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'plan_type', 'payment_status'],
                ],
            ]);
    }

    public function test_admin_can_filter_subscriptions_by_plan(): void
    {
        Sanctum::actingAs($this->admin);

        $event1 = Event::factory()->create(['user_id' => $this->user->id]);
        $event2 = Event::factory()->create(['user_id' => $this->user->id]);

        Subscription::factory()->starter()->pending()->create(['event_id' => $event1->id]);
        Subscription::factory()->pro()->pending()->create(['event_id' => $event2->id]);

        $response = $this->getJson('/api/admin/subscriptions?plan=pro');

        $response->assertOk();
        foreach ($response->json('data') as $subscription) {
            $this->assertEquals('pro', $subscription['plan_type']);
        }
    }
}
