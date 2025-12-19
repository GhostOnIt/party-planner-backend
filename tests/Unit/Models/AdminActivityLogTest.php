<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_activity_log_belongs_to_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $log = AdminActivityLog::factory()->forAdmin($admin)->create();

        $this->assertInstanceOf(User::class, $log->admin);
        $this->assertEquals($admin->id, $log->admin->id);
    }

    public function test_can_create_activity_log(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $log = AdminActivityLog::create([
            'admin_id' => $admin->id,
            'action' => 'test_action',
            'description' => 'Test description',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
        ]);

        $this->assertDatabaseHas('admin_activity_logs', [
            'id' => $log->id,
            'admin_id' => $admin->id,
            'action' => 'test_action',
            'description' => 'Test description',
        ]);
    }

    public function test_log_static_method_creates_entry(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($admin);

        $targetUser = User::factory()->create();

        $log = AdminActivityLog::log(
            'update',
            'Updated user profile',
            $targetUser,
            ['name' => 'Old Name'],
            ['name' => 'New Name']
        );

        $this->assertDatabaseHas('admin_activity_logs', [
            'id' => $log->id,
            'admin_id' => $admin->id,
            'action' => 'update',
            'description' => 'Updated user profile',
            'model_type' => User::class,
            'model_id' => $targetUser->id,
        ]);

        $this->assertEquals(['name' => 'Old Name'], $log->old_values);
        $this->assertEquals(['name' => 'New Name'], $log->new_values);
    }

    public function test_scope_by_admin_filters_correctly(): void
    {
        $admin1 = User::factory()->create(['role' => UserRole::ADMIN]);
        $admin2 = User::factory()->create(['role' => UserRole::ADMIN]);

        AdminActivityLog::factory()->count(3)->forAdmin($admin1)->create();
        AdminActivityLog::factory()->count(2)->forAdmin($admin2)->create();

        $logs = AdminActivityLog::byAdmin($admin1->id)->get();

        $this->assertCount(3, $logs);
        $logs->each(fn($log) => $this->assertEquals($admin1->id, $log->admin_id));
    }

    public function test_scope_for_model_filters_correctly(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $targetUser = User::factory()->create();

        AdminActivityLog::factory()->forAdmin($admin)->create([
            'model_type' => User::class,
            'model_id' => $targetUser->id,
        ]);

        AdminActivityLog::factory()->forAdmin($admin)->create([
            'model_type' => User::class,
            'model_id' => 999,
        ]);

        AdminActivityLog::factory()->forAdmin($admin)->create([
            'model_type' => null,
            'model_id' => null,
        ]);

        // Test filtering by model type only
        $logsByType = AdminActivityLog::forModel(User::class)->get();
        $this->assertCount(2, $logsByType);

        // Test filtering by model type and ID
        $logsByTypeAndId = AdminActivityLog::forModel(User::class, $targetUser->id)->get();
        $this->assertCount(1, $logsByTypeAndId);
    }

    public function test_scope_recent_orders_by_date(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $oldLog = AdminActivityLog::factory()->forAdmin($admin)->create([
            'created_at' => now()->subDays(5),
        ]);

        $newLog = AdminActivityLog::factory()->forAdmin($admin)->create([
            'created_at' => now(),
        ]);

        $logs = AdminActivityLog::recent(10)->get();

        $this->assertEquals($newLog->id, $logs->first()->id);
        $this->assertEquals($oldLog->id, $logs->last()->id);
    }

    public function test_old_values_and_new_values_are_cast_to_array(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $log = AdminActivityLog::create([
            'admin_id' => $admin->id,
            'action' => 'update',
            'description' => 'Test',
            'old_values' => ['role' => 'user'],
            'new_values' => ['role' => 'admin'],
        ]);

        $log->refresh();

        $this->assertIsArray($log->old_values);
        $this->assertIsArray($log->new_values);
        $this->assertEquals(['role' => 'user'], $log->old_values);
        $this->assertEquals(['role' => 'admin'], $log->new_values);
    }

    public function test_scope_by_action_filters_correctly(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        AdminActivityLog::factory()->forAdmin($admin)->create(['action' => 'create']);
        AdminActivityLog::factory()->forAdmin($admin)->create(['action' => 'update']);
        AdminActivityLog::factory()->forAdmin($admin)->create(['action' => 'delete']);

        $createLogs = AdminActivityLog::byAction('create')->get();
        $this->assertCount(1, $createLogs);
        $this->assertEquals('create', $createLogs->first()->action);
    }

    public function test_scope_search_filters_by_description(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        AdminActivityLog::factory()->forAdmin($admin)->create([
            'description' => 'Modification du profil utilisateur',
        ]);

        AdminActivityLog::factory()->forAdmin($admin)->create([
            'description' => 'Création de template',
        ]);

        $logs = AdminActivityLog::search('profil')->get();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('profil', $logs->first()->description);
    }
}
