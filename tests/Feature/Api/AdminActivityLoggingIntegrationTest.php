<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminActivityLoggingIntegrationTest extends TestCase
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
    | DashboardController Logging Tests
    |--------------------------------------------------------------------------
    */

    public function test_updating_user_role_creates_activity_log(): void
    {
        Sanctum::actingAs($this->admin);

        $targetUser = User::factory()->create(['role' => UserRole::USER]);

        $response = $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role' => 'admin',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'update_role',
            'model_type' => User::class,
            'model_id' => $targetUser->id,
        ]);
    }

    public function test_deleting_user_creates_activity_log(): void
    {
        Sanctum::actingAs($this->admin);

        $targetUser = User::factory()->create(['role' => UserRole::USER]);
        $targetUserId = $targetUser->id;

        $response = $this->deleteJson("/api/admin/users/{$targetUser->id}");

        $response->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'delete',
            'model_type' => User::class,
            'model_id' => $targetUserId,
        ]);
    }

    public function test_activity_log_contains_old_and_new_values(): void
    {
        Sanctum::actingAs($this->admin);

        $targetUser = User::factory()->create(['role' => UserRole::USER]);

        $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role' => 'admin',
        ]);

        $log = AdminActivityLog::where('model_id', $targetUser->id)
            ->where('action', 'update_role')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->old_values);
        $this->assertNotNull($log->new_values);
        $this->assertEquals('user', $log->old_values['role']);
        $this->assertEquals('admin', $log->new_values['role']);
    }

    /*
    |--------------------------------------------------------------------------
    | EventTemplateController Logging Tests
    |--------------------------------------------------------------------------
    */

    public function test_creating_template_creates_activity_log(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/templates', [
            'event_type' => 'mariage',
            'name' => 'Test Template',
            'description' => 'Test description',
        ]);

        $response->assertCreated();

        $templateId = $response->json('template.id');

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'create',
            'model_type' => EventTemplate::class,
            'model_id' => $templateId,
        ]);
    }

    public function test_updating_template_creates_activity_log(): void
    {
        Sanctum::actingAs($this->admin);

        $template = EventTemplate::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/admin/templates/{$template->id}", [
            'event_type' => 'mariage',
            'name' => 'New Name',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'update',
            'model_type' => EventTemplate::class,
            'model_id' => $template->id,
        ]);
    }

    public function test_deleting_template_creates_activity_log(): void
    {
        Sanctum::actingAs($this->admin);

        $template = EventTemplate::factory()->create();
        $templateId = $template->id;

        $response = $this->deleteJson("/api/admin/templates/{$template->id}");

        $response->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'delete',
            'model_type' => EventTemplate::class,
            'model_id' => $templateId,
        ]);
    }

    public function test_toggling_template_active_creates_activity_log(): void
    {
        Sanctum::actingAs($this->admin);

        $template = EventTemplate::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/admin/templates/{$template->id}/toggle-active");

        $response->assertOk();

        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'toggle_active',
            'model_type' => EventTemplate::class,
            'model_id' => $template->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | IP and User-Agent Capture Tests
    |--------------------------------------------------------------------------
    */

    public function test_activity_log_captures_ip_address(): void
    {
        Sanctum::actingAs($this->admin);

        $targetUser = User::factory()->create(['role' => UserRole::USER]);

        $this->putJson("/api/admin/users/{$targetUser->id}/role", [
            'role' => 'admin',
        ]);

        $log = AdminActivityLog::where('model_id', $targetUser->id)->first();

        $this->assertNotNull($log->ip_address);
    }

    public function test_activity_log_captures_user_agent(): void
    {
        Sanctum::actingAs($this->admin);

        $targetUser = User::factory()->create(['role' => UserRole::USER]);

        $this->withHeaders(['User-Agent' => 'Test Browser/1.0'])
            ->putJson("/api/admin/users/{$targetUser->id}/role", [
                'role' => 'admin',
            ]);

        $log = AdminActivityLog::where('model_id', $targetUser->id)->first();

        $this->assertNotNull($log->user_agent);
    }
}
