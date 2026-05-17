<?php

namespace Tests\Feature\Api;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\UserRole;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\User;
use App\Services\PhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class EventControllerTest extends TestCase
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

    public function test_user_can_list_their_events(): void
    {
        Sanctum::actingAs($this->user);

        Event::factory()->count(3)->create(['user_id' => $this->user->id]);
        Event::factory()->count(2)->create(); // Other user's events

        $response = $this->getJson('/api/events');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_list_all_events(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        Event::factory()->count(3)->create(['user_id' => $this->user->id]);
        Event::factory()->count(2)->create();

        $response = $this->getJson('/api/events');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_unauthenticated_user_cannot_list_events(): void
    {
        $response = $this->getJson('/api/events');

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Store Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_create_event(): void
    {
        Sanctum::actingAs($this->user);

        $eventData = [
            'title' => 'Mon anniversaire',
            'type' => EventType::ANNIVERSAIRE->value,
            'description' => 'Une super fête',
            'date' => now()->addMonth()->format('Y-m-d'),
            'time' => '18:00',
            'location' => 'Paris',
            'estimated_budget' => 50000,
            'theme' => 'Tropical',
            'expected_guests_count' => 50,
        ];

        $response = $this->postJson('/api/events', $eventData);

        $response->assertCreated()
            ->assertJsonFragment(['title' => 'Mon anniversaire']);

        $this->assertDatabaseHas('events', [
            'title' => 'Mon anniversaire',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_create_event_requires_title(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/events', [
            'type' => EventType::ANNIVERSAIRE->value,
            'date' => now()->addMonth()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_event_requires_valid_type(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/events', [
            'title' => 'Test Event',
            'type' => 'invalid_type',
            'date' => now()->addMonth()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_event_requires_future_date(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/events', [
            'title' => 'Test Event',
            'type' => EventType::ANNIVERSAIRE->value,
            'date' => now()->subDay()->format('Y-m-d'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_view_their_event(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $event->id]);
    }

    public function test_user_cannot_view_other_users_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->getJson("/api/events/{$otherEvent->id}");

        $response->assertForbidden();
    }

    public function test_collaborator_can_view_shared_event(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create();
        Collaborator::factory()->create([
            'event_id' => $event->id,
            'user_id' => $this->user->id,
            'role' => 'viewer',
        ]);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertOk();
    }

    public function test_admin_can_view_any_event(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $event = Event::factory()->create();

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Update Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_update_their_event(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/events/{$event->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Updated Title']);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_user_cannot_update_other_users_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->putJson("/api/events/{$otherEvent->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertForbidden();
    }

    public function test_editor_collaborator_can_update_event(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create();
        Collaborator::factory()->create([
            'event_id' => $event->id,
            'user_id' => $this->user->id,
            'role' => 'editor',
        ]);

        $response = $this->putJson("/api/events/{$event->id}", [
            'title' => 'Collaborator Update',
        ]);

        $response->assertOk();
    }

    public function test_viewer_collaborator_cannot_update_event(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create();
        Collaborator::factory()->create([
            'event_id' => $event->id,
            'user_id' => $this->user->id,
            'role' => 'viewer',
        ]);

        $response = $this->putJson("/api/events/{$event->id}", [
            'title' => 'Viewer Update',
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_update_event_status(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'status' => EventStatus::UPCOMING->value,
        ]);

        $response = $this->putJson("/api/events/{$event->id}", [
            'status' => EventStatus::CANCELLED->value,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => EventStatus::CANCELLED->value]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_delete_their_event(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/events/{$event->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_user_cannot_delete_other_users_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->deleteJson("/api/events/{$otherEvent->id}");

        $response->assertForbidden();
    }

    public function test_collaborator_cannot_delete_event(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create();
        Collaborator::factory()->create([
            'event_id' => $event->id,
            'user_id' => $this->user->id,
            'role' => 'editor',
        ]);

        $response = $this->deleteJson("/api/events/{$event->id}");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Public Show Tests
    |--------------------------------------------------------------------------
    */

    public function test_anyone_can_view_public_event_details(): void
    {
        $event = Event::factory()->create([
            'title' => 'Public Event',
            'location' => 'Paris',
        ]);

        $response = $this->getJson("/api/events/{$event->id}/public");

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Public Event'])
            ->assertJsonMissing(['description']); // Should not include all details
    }

    /*
    |--------------------------------------------------------------------------
    | Cover photo upload tests
    |--------------------------------------------------------------------------
    */

    public function test_create_event_with_cover_photo_uploads_and_marks_as_featured(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/events', [
            'title' => 'Avec photo',
            'type' => EventType::ANNIVERSAIRE->value,
            'date' => now()->addMonth()->format('Y-m-d'),
            'time' => '18:00',
            'location' => 'Brazzaville',
            'expected_guests_count' => 30,
            'cover_photo' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['event', 'quota'])
            ->assertJsonMissing(['warning']);

        $this->assertDatabaseHas('events', ['title' => 'Avec photo']);
        $this->assertDatabaseHas('photos', ['is_featured' => true]);
    }

    public function test_create_event_returns_warning_when_cover_photo_upload_fails(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        // Force PhotoService::upload to throw, simulating a storage outage.
        $this->mock(PhotoService::class, function (MockInterface $mock) {
            $mock->shouldReceive('upload')
                ->once()
                ->andThrow(new \RuntimeException('Storage unreachable'));
            // setAsFeatured must NOT be reached.
            $mock->shouldReceive('setAsFeatured')->never();
        });

        $response = $this->postJson('/api/events', [
            'title' => 'Photo cassée',
            'type' => EventType::ANNIVERSAIRE->value,
            'date' => now()->addMonth()->format('Y-m-d'),
            'time' => '18:00',
            'location' => 'Brazzaville',
            'expected_guests_count' => 30,
            'cover_photo' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ]);

        // Event still created, warning surfaced — no duplicate on retry.
        $response->assertCreated()
            ->assertJsonStructure(['event', 'quota', 'warning']);

        $this->assertStringContainsString('photo de couverture', $response->json('warning'));
        $this->assertDatabaseHas('events', ['title' => 'Photo cassée']);
        $this->assertDatabaseMissing('photos', ['is_featured' => true]);
    }

    public function test_update_event_returns_warning_when_cover_photo_upload_fails(): void
    {
        Sanctum::actingAs($this->user);

        $event = Event::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Original',
        ]);

        $this->mock(PhotoService::class, function (MockInterface $mock) {
            $mock->shouldReceive('upload')
                ->once()
                ->andThrow(new \RuntimeException('Storage unreachable'));
            $mock->shouldReceive('setAsFeatured')->never();
        });

        $response = $this->putJson("/api/events/{$event->id}", [
            'title' => 'Modifié',
            'cover_photo' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['event', 'warning']);

        $this->assertStringContainsString('photo de couverture', $response->json('warning'));
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Modifié',
        ]);
    }

    public function test_create_event_rejects_oversized_cover_photo_before_creating_event(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Sanctum::actingAs($admin);

        // Max size is read from config — produce a clearly oversized file.
        $oversized = UploadedFile::fake()->create('huge.jpg', 50_000, 'image/jpeg');

        $response = $this->postJson('/api/events', [
            'title' => 'Trop gros',
            'type' => EventType::ANNIVERSAIRE->value,
            'date' => now()->addMonth()->format('Y-m-d'),
            'time' => '18:00',
            'location' => 'Brazzaville',
            'expected_guests_count' => 30,
            'cover_photo' => $oversized,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cover_photo']);

        $this->assertDatabaseMissing('events', ['title' => 'Trop gros']);
    }
}
