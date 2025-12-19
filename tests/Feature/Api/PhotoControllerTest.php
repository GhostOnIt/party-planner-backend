<?php

namespace Tests\Feature\Api;

use App\Enums\PhotoType;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhotoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(['user_id' => $this->user->id]);
        Storage::fake('public');
    }

    /*
    |--------------------------------------------------------------------------
    | Index Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_list_photos(): void
    {
        Sanctum::actingAs($this->user);

        Photo::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/photos");

        $response->assertOk()
            ->assertJsonStructure(['photos', 'stats', 'can_add_photos', 'remaining_slots']);
    }

    public function test_user_cannot_list_photos_for_other_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->getJson("/api/events/{$otherEvent->id}/photos");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Statistics Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_get_photo_statistics(): void
    {
        Sanctum::actingAs($this->user);

        Photo::factory()->count(3)->create([
            'event_id' => $this->event->id,
            'type' => PhotoType::MOODBOARD->value,
        ]);
        Photo::factory()->count(2)->create([
            'event_id' => $this->event->id,
            'type' => PhotoType::EVENT_PHOTO->value,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/photos/statistics");

        $response->assertOk()
            ->assertJsonStructure([
                'stats',
                'can_add_photos',
                'remaining_slots',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_upload_photos(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/photos", [
            'photos' => [
                UploadedFile::fake()->image('photo1.jpg', 800, 600),
                UploadedFile::fake()->image('photo2.jpg', 800, 600),
            ],
            'type' => PhotoType::MOODBOARD->value,
            'description' => 'Test photos',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('photos', 2);
    }

    public function test_upload_requires_photos(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/photos", [
            'type' => PhotoType::MOODBOARD->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photos']);
    }

    public function test_upload_requires_valid_type(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/photos", [
            'photos' => [UploadedFile::fake()->image('photo.jpg')],
            'type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_upload_rejects_non_image_files(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/photos", [
            'photos' => [UploadedFile::fake()->create('document.pdf', 100)],
            'type' => PhotoType::MOODBOARD->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photos.0']);
    }

    public function test_viewer_cannot_upload_photos(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/photos", [
            'photos' => [UploadedFile::fake()->image('photo.jpg')],
            'type' => PhotoType::MOODBOARD->value,
        ]);

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Show Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_view_photo(): void
    {
        Sanctum::actingAs($this->user);

        $photo = Photo::factory()->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/photos/{$photo->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $photo->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_update_photo_description(): void
    {
        Sanctum::actingAs($this->user);

        $photo = Photo::factory()->create([
            'event_id' => $this->event->id,
            'description' => 'Original description',
        ]);

        $response = $this->putJson("/api/events/{$this->event->id}/photos/{$photo->id}", [
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['description' => 'Updated description']);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_delete_photo(): void
    {
        Sanctum::actingAs($this->user);

        $photo = Photo::factory()->create(['event_id' => $this->event->id]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/photos/{$photo->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
    }

    public function test_viewer_cannot_delete_photo(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        $photo = Photo::factory()->create(['event_id' => $this->event->id]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/photos/{$photo->id}");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Featured Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_toggle_featured_photo(): void
    {
        Sanctum::actingAs($this->user);

        $photo = Photo::factory()->create([
            'event_id' => $this->event->id,
            'is_featured' => false,
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/photos/{$photo->id}/toggle-featured");

        $response->assertOk()
            ->assertJsonFragment(['is_featured' => true]);
    }

    public function test_user_can_set_featured_photo(): void
    {
        Sanctum::actingAs($this->user);

        $photo1 = Photo::factory()->create([
            'event_id' => $this->event->id,
            'is_featured' => true,
        ]);
        $photo2 = Photo::factory()->create([
            'event_id' => $this->event->id,
            'is_featured' => false,
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/photos/{$photo2->id}/set-featured");

        $response->assertOk();

        // The new photo should be featured
        $this->assertDatabaseHas('photos', [
            'id' => $photo2->id,
            'is_featured' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Operations Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_bulk_delete_photos(): void
    {
        Sanctum::actingAs($this->user);

        $photos = Photo::factory()->count(3)->create(['event_id' => $this->event->id]);
        $photoIds = $photos->pluck('id')->toArray();

        $response = $this->postJson("/api/events/{$this->event->id}/photos/bulk-delete", [
            'photos' => $photoIds, // API uses 'photos' not 'photo_ids'
        ]);

        $response->assertOk();

        foreach ($photoIds as $id) {
            $this->assertDatabaseMissing('photos', ['id' => $id]);
        }
    }

    public function test_user_can_bulk_update_photo_type(): void
    {
        Sanctum::actingAs($this->user);

        $photos = Photo::factory()->count(3)->create([
            'event_id' => $this->event->id,
            'type' => PhotoType::MOODBOARD->value,
        ]);
        $photoIds = $photos->pluck('id')->toArray();

        $response = $this->postJson("/api/events/{$this->event->id}/photos/bulk-update-type", [
            'photos' => $photoIds, // API uses 'photos' not 'photo_ids'
            'type' => PhotoType::EVENT_PHOTO->value,
        ]);

        $response->assertOk();

        foreach ($photoIds as $id) {
            $this->assertDatabaseHas('photos', [
                'id' => $id,
                'type' => PhotoType::EVENT_PHOTO->value,
            ]);
        }
    }
}
