<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Middleware Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/stats');

        $response->assertOk();
    }

    public function test_regular_user_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/stats');

        $response->assertForbidden()
            ->assertJson(['message' => 'Accès réservé aux administrateurs.']);
    }

    public function test_unauthenticated_user_cannot_access_admin_routes(): void
    {
        $response = $this->getJson('/api/admin/stats');

        $response->assertUnauthorized();
    }

    public function test_admin_can_access_admin_users_route(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users');

        $response->assertOk();
    }

    public function test_regular_user_cannot_access_admin_users_route(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_events_route(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/events');

        $response->assertOk();
    }

    public function test_regular_user_cannot_access_admin_events_route(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/events');

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_payments_route(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/payments');

        $response->assertOk();
    }

    public function test_admin_can_access_admin_subscriptions_route(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/subscriptions');

        $response->assertOk();
    }

    public function test_admin_can_access_admin_templates_route(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/templates');

        $response->assertOk();
    }
}
