<?php

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\AdminActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AdminActivityService $service;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminActivityService();
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
    }

    public function test_log_action_creates_activity_log(): void
    {
        $this->actingAs($this->admin);

        $targetUser = User::factory()->create();

        $log = $this->service->logAction(
            'update',
            'Updated user profile',
            $targetUser,
            ['old' => ['name' => 'Old'], 'new' => ['name' => 'New']]
        );

        $this->assertInstanceOf(AdminActivityLog::class, $log);
        $this->assertEquals('update', $log->action);
        $this->assertEquals('Updated user profile', $log->description);
        $this->assertEquals(User::class, $log->model_type);
        $this->assertEquals($targetUser->id, $log->model_id);
    }

    public function test_log_login_records_admin_login(): void
    {
        $log = $this->service->logLogin($this->admin);

        $this->assertEquals('login', $log->action);
        $this->assertEquals($this->admin->id, $log->admin_id);
        $this->assertStringContainsString($this->admin->name, $log->description);
        $this->assertNull($log->model_type);
    }

    public function test_log_user_action_records_user_changes(): void
    {
        $this->actingAs($this->admin);

        $targetUser = User::factory()->create(['name' => 'Test User']);

        $log = $this->service->logUserAction('update_role', $targetUser, [
            'old' => ['role' => 'user'],
            'new' => ['role' => 'admin'],
        ]);

        $this->assertEquals('update_role', $log->action);
        $this->assertEquals(User::class, $log->model_type);
        $this->assertEquals($targetUser->id, $log->model_id);
        $this->assertStringContainsString($targetUser->name, $log->description);
    }

    public function test_log_event_action_records_event_changes(): void
    {
        $this->actingAs($this->admin);

        $event = Event::factory()->create(['title' => 'Test Event']);

        $log = $this->service->logEventAction('view', $event);

        $this->assertEquals('view', $log->action);
        $this->assertEquals(Event::class, $log->model_type);
        $this->assertEquals($event->id, $log->model_id);
        $this->assertStringContainsString('Test Event', $log->description);
    }

    public function test_log_template_action_records_template_changes(): void
    {
        $this->actingAs($this->admin);

        $template = EventTemplate::factory()->create(['name' => 'Test Template']);

        $log = $this->service->logTemplateAction('create', $template, [
            'old' => null,
            'new' => $template->toArray(),
        ]);

        $this->assertEquals('create', $log->action);
        $this->assertEquals(EventTemplate::class, $log->model_type);
        $this->assertEquals($template->id, $log->model_id);
        $this->assertStringContainsString('Test Template', $log->description);
    }

    public function test_get_activity_for_admin_returns_paginated_results(): void
    {
        AdminActivityLog::factory()->count(20)->forAdmin($this->admin)->create();

        $results = $this->service->getActivityForAdmin($this->admin->id, 10);

        $this->assertCount(10, $results->items());
        $this->assertEquals(20, $results->total());
    }

    public function test_get_recent_activity_returns_limited_results(): void
    {
        AdminActivityLog::factory()->count(20)->forAdmin($this->admin)->create();

        $results = $this->service->getRecentActivity(5);

        $this->assertCount(5, $results);
    }

    public function test_get_activity_stats_returns_correct_counts(): void
    {
        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'action' => 'create',
            'created_at' => now(),
        ]);

        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'action' => 'update',
            'created_at' => now(),
        ]);

        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'action' => 'delete',
            'created_at' => now()->subDays(10),
        ]);

        $stats = $this->service->getActivityStats();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['today']);
        $this->assertArrayHasKey('by_action', $stats);
        $this->assertArrayHasKey('by_admin', $stats);
    }

    public function test_get_activity_logs_with_filters(): void
    {
        $otherAdmin = User::factory()->create(['role' => UserRole::ADMIN]);

        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'action' => 'create',
            'model_type' => User::class,
        ]);

        AdminActivityLog::factory()->forAdmin($this->admin)->create([
            'action' => 'update',
            'model_type' => Event::class,
        ]);

        AdminActivityLog::factory()->forAdmin($otherAdmin)->create([
            'action' => 'delete',
        ]);

        // Filter by admin
        $results = $this->service->getActivityLogs(['admin_id' => $this->admin->id]);
        $this->assertEquals(2, $results->total());

        // Filter by action
        $results = $this->service->getActivityLogs(['action' => 'create']);
        $this->assertEquals(1, $results->total());

        // Filter by model_type
        $results = $this->service->getActivityLogs(['model_type' => User::class]);
        $this->assertEquals(1, $results->total());
    }
}
