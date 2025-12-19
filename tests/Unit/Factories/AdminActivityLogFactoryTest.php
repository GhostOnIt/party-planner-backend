<?php

namespace Tests\Unit\Factories;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityLogFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_activity_log(): void
    {
        $log = AdminActivityLog::factory()->create();

        $this->assertDatabaseHas('admin_activity_logs', [
            'id' => $log->id,
        ]);

        $this->assertNotNull($log->admin_id);
        $this->assertNotNull($log->action);
        $this->assertNotNull($log->description);
    }

    public function test_factory_user_action_state(): void
    {
        $log = AdminActivityLog::factory()->userAction()->create();

        $this->assertEquals(User::class, $log->model_type);
        $this->assertNotNull($log->model_id);
        $this->assertContains($log->action, ['view', 'update', 'update_role', 'delete']);
    }

    public function test_factory_template_action_state(): void
    {
        $log = AdminActivityLog::factory()->templateAction()->create();

        $this->assertEquals(EventTemplate::class, $log->model_type);
        $this->assertNotNull($log->model_id);
        $this->assertContains($log->action, ['view', 'create', 'update', 'delete', 'toggle_active']);
    }

    public function test_factory_event_action_state(): void
    {
        $log = AdminActivityLog::factory()->eventAction()->create();

        $this->assertEquals(Event::class, $log->model_type);
        $this->assertNotNull($log->model_id);
        $this->assertContains($log->action, ['view', 'create', 'update', 'delete']);
    }

    public function test_factory_login_state(): void
    {
        $log = AdminActivityLog::factory()->login()->create();

        $this->assertEquals('login', $log->action);
        $this->assertNull($log->model_type);
        $this->assertNull($log->model_id);
        $this->assertStringContainsString('Connexion', $log->description);
    }

    public function test_factory_for_admin_state(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $log = AdminActivityLog::factory()->forAdmin($admin)->create();

        $this->assertEquals($admin->id, $log->admin_id);
    }

    public function test_factory_created_at_state(): void
    {
        $date = now()->subDays(5);
        $log = AdminActivityLog::factory()->createdAt($date)->create();

        $this->assertEquals($date->format('Y-m-d H:i:s'), $log->created_at->format('Y-m-d H:i:s'));
    }
}
