<?php

namespace Tests\Feature\Api;

use App\Enums\NotificationType;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Index Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_list_their_notifications(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(5)->create(['user_id' => $this->user->id]);

        // Create notifications for another user
        Notification::factory()->count(3)->create();

        $response = $this->getJson('/api/notifications');

        $response->assertOk();
    }

    public function test_user_can_filter_notifications_by_type(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'type' => NotificationType::TASK_REMINDER->value,
        ]);
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'type' => NotificationType::BUDGET_ALERT->value,
        ]);

        $response = $this->getJson('/api/notifications?type=' . NotificationType::TASK_REMINDER->value);

        $response->assertOk();
    }

    public function test_user_can_filter_unread_notifications(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(3)->unread()->create(['user_id' => $this->user->id]);
        Notification::factory()->count(2)->read()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/notifications?unread=1');

        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_list_notifications(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Mark As Read Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_mark_notification_as_read(): void
    {
        Sanctum::actingAs($this->user);

        $notification = Notification::factory()->unread()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_other_user_notification_as_read(): void
    {
        Sanctum::actingAs($this->user);

        $notification = Notification::factory()->unread()->create();

        $response = $this->putJson("/api/notifications/{$notification->id}/read");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Mark All As Read Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(5)->unread()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson('/api/notifications/read-all');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(0, Notification::where('user_id', $this->user->id)
            ->whereNull('read_at')
            ->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Unread Count Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_get_unread_count(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(3)->unread()->create([
            'user_id' => $this->user->id,
        ]);
        Notification::factory()->count(2)->read()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/notifications/unread-count');

        $response->assertOk()
            ->assertJsonStructure(['count']);
    }

    /*
    |--------------------------------------------------------------------------
    | Recent Notifications Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_get_recent_notifications(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(10)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/notifications/recent?limit=5');

        $response->assertOk()
            ->assertJsonStructure(['notifications', 'unread_count']);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_delete_notification(): void
    {
        Sanctum::actingAs($this->user);

        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/notifications/{$notification->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_delete_other_user_notification(): void
    {
        Sanctum::actingAs($this->user);

        $notification = Notification::factory()->create();

        $response = $this->deleteJson("/api/notifications/{$notification->id}");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Delete Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_bulk_delete_notifications(): void
    {
        Sanctum::actingAs($this->user);

        $notifications = Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/notifications/bulk-delete', [
            'notifications' => $notifications->pluck('id')->toArray(),
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_bulk_delete_requires_notifications_array(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/notifications/bulk-delete', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notifications']);
    }

    /*
    |--------------------------------------------------------------------------
    | Clear Read Notifications Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_clear_read_notifications(): void
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(3)->read()->create([
            'user_id' => $this->user->id,
        ]);
        Notification::factory()->count(2)->unread()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson('/api/notifications/clear-read');

        $response->assertOk()
            ->assertJson(['success' => true]);

        // Only unread notifications should remain
        $this->assertEquals(2, Notification::where('user_id', $this->user->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Settings Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_view_notification_settings(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/notifications/settings');

        $response->assertOk();
    }

    public function test_user_can_update_notification_settings(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/notifications/settings', [
            'task_reminder' => true,
            'guest_reminder' => false,
            'budget_alert' => true,
            'event_reminder' => true,
            'collaboration_invite' => true,
            'email_notifications' => true,
            'push_notifications' => false,
        ]);

        $response->assertOk();
    }
}
