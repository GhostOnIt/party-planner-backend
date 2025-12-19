<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
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
    | Activity Logs List Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_activity_logs(): void
    {
        Sanctum::actingAs($this->admin);

        AdminActivityLog::factory()->count(5)->forAdmin($this->admin)->create();

        $response = $this->getJson('/api/admin/activity-logs');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'admin_id', 'action', 'description', 'created_at'],
                ],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_admin_can_filter_logs_by_action(): void
    {
        Sanctum::actingAs($this->admin);

        AdminActivityLog::factory()->forAdmin($this->admin)->create(['action' => 'create']);
        AdminActivityLog::factory()->forAdmin($this->admin)->create(['action' => 'update']);
        AdminActivityLog::factory()->forAdmin($this->admin)->create(['action' => 'delete']);

        $response = $this->getJson('/api/admin/activity-logs?action=create');

        $response->assertOk();
        foreach ($response->json('data') as $log) {
            $this->assertEquals('create', $log['action']);
        }
    }

    public function test_admin_can_filter_logs_by_model_type(): void
    {
        Sanctum::actingAs($this->admin);

        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'model_type' => User::class,
        ]);
        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'model_type' => null,
        ]);

        $response = $this->getJson('/api/admin/activity-logs?model_type=' . urlencode(User::class));

        $response->assertOk();
        foreach ($response->json('data') as $log) {
            $this->assertEquals(User::class, $log['model_type']);
        }
    }

    public function test_admin_can_filter_logs_by_date_range(): void
    {
        Sanctum::actingAs($this->admin);

        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'created_at' => now()->subDays(10),
        ]);
        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'created_at' => now()->subDays(2),
        ]);

        $from = now()->subDays(5)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->getJson("/api/admin/activity-logs?from={$from}&to={$to}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_search_logs_by_description(): void
    {
        Sanctum::actingAs($this->admin);

        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'description' => 'Modification du template Test',
        ]);
        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'description' => 'Suppression utilisateur',
        ]);

        $response = $this->getJson('/api/admin/activity-logs?search=template');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_regular_user_cannot_access_activity_logs(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/admin/activity-logs');

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Stats Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_get_activity_stats(): void
    {
        Sanctum::actingAs($this->admin);

        AdminActivityLog::factory()->count(5)->forAdmin($this->admin)->create();

        $response = $this->getJson('/api/admin/activity-logs/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'total',
                    'today',
                    'this_week',
                    'this_month',
                    'by_action',
                    'by_admin',
                ],
            ]);
    }

    public function test_activity_stats_returns_correct_structure(): void
    {
        Sanctum::actingAs($this->admin);

        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'action' => 'create',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/activity-logs/stats');

        $response->assertOk();

        $stats = $response->json('stats');
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('today', $stats);
        $this->assertArrayHasKey('this_week', $stats);
        $this->assertArrayHasKey('this_month', $stats);
        $this->assertArrayHasKey('by_action', $stats);
        $this->assertArrayHasKey('by_model_type', $stats);
        $this->assertArrayHasKey('by_admin', $stats);
    }

    public function test_regular_user_cannot_access_activity_stats(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/admin/activity-logs/stats');

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_activity_logs(): void
    {
        $response = $this->getJson('/api/admin/activity-logs');

        $response->assertUnauthorized();
    }
}
