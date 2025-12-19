<?php

namespace Tests\Feature\Api;

use App\Enums\RsvpStatus;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create(['user_id' => $this->user->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Index Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_list_guests_for_their_event(): void
    {
        Sanctum::actingAs($this->user);

        Guest::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/guests");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_user_cannot_list_guests_for_other_users_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();
        Guest::factory()->count(3)->create(['event_id' => $otherEvent->id]);

        $response = $this->getJson("/api/events/{$otherEvent->id}/guests");

        $response->assertForbidden();
    }

    public function test_collaborator_can_list_guests(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        Guest::factory()->count(3)->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/guests");

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Store Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_add_guest_to_their_event(): void
    {
        Sanctum::actingAs($this->user);

        $guestData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+33612345678',
            'notes' => 'VIP guest',
        ];

        $response = $this->postJson("/api/events/{$this->event->id}/guests", $guestData);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'John Doe']);

        $this->assertDatabaseHas('guests', [
            'event_id' => $this->event->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_create_guest_requires_name(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/guests", [
            'email' => 'test@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_guest_validates_email_format(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/guests", [
            'name' => 'Test Guest',
            'email' => 'invalid-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_add_guest_to_other_users_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->postJson("/api/events/{$otherEvent->id}/guests", [
            'name' => 'Hacker Guest',
        ]);

        $response->assertForbidden();
    }

    public function test_editor_collaborator_can_add_guest(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'editor',
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/guests", [
            'name' => 'Collaborator Guest',
        ]);

        $response->assertCreated();
    }

    public function test_viewer_collaborator_cannot_add_guest(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/guests", [
            'name' => 'Viewer Guest',
        ]);

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Show Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_view_guest_details(): void
    {
        Sanctum::actingAs($this->user);

        $guest = Guest::factory()->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/guests/{$guest->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $guest->id]);
    }

    public function test_user_cannot_view_guest_from_other_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();
        $guest = Guest::factory()->create(['event_id' => $otherEvent->id]);

        $response = $this->getJson("/api/events/{$otherEvent->id}/guests/{$guest->id}");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Update Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_update_guest(): void
    {
        Sanctum::actingAs($this->user);

        $guest = Guest::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Original Name',
        ]);

        $response = $this->putJson("/api/events/{$this->event->id}/guests/{$guest->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('guests', [
            'id' => $guest->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_update_guest_rsvp_status(): void
    {
        Sanctum::actingAs($this->user);

        $guest = Guest::factory()->pending()->create(['event_id' => $this->event->id]);

        $response = $this->putJson("/api/events/{$this->event->id}/guests/{$guest->id}", [
            'name' => $guest->name,
            'rsvp_status' => RsvpStatus::ACCEPTED->value,
        ]);

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_delete_guest(): void
    {
        Sanctum::actingAs($this->user);

        $guest = Guest::factory()->create(['event_id' => $this->event->id]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/guests/{$guest->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
    }

    public function test_user_cannot_delete_guest_from_other_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();
        $guest = Guest::factory()->create(['event_id' => $otherEvent->id]);

        $response = $this->deleteJson("/api/events/{$otherEvent->id}/guests/{$guest->id}");

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_guest(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        $guest = Guest::factory()->create(['event_id' => $this->event->id]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/guests/{$guest->id}");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Statistics Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_get_guest_statistics(): void
    {
        Sanctum::actingAs($this->user);

        // Create guests with different statuses
        Guest::factory()->count(3)->accepted()->create(['event_id' => $this->event->id]);
        Guest::factory()->count(2)->declined()->create(['event_id' => $this->event->id]);
        Guest::factory()->count(4)->pending()->create(['event_id' => $this->event->id]);
        Guest::factory()->create([
            'event_id' => $this->event->id,
            'rsvp_status' => RsvpStatus::MAYBE,
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/guests/statistics");

        $response->assertOk()
            ->assertJsonStructure([
                'statistics' => [
                    'total',
                    'by_status' => ['accepted', 'declined', 'pending', 'maybe'],
                    'invitations' => ['sent', 'not_sent'],
                    'check_in' => ['checked_in', 'not_checked_in'],
                    'with_email',
                    'without_email',
                ],
                'can_add_more',
                'remaining_slots',
            ]);

        $stats = $response->json('statistics');
        $this->assertEquals(10, $stats['total']);
        $this->assertEquals(3, $stats['by_status']['accepted']);
        $this->assertEquals(2, $stats['by_status']['declined']);
        $this->assertEquals(4, $stats['by_status']['pending']);
        $this->assertEquals(1, $stats['by_status']['maybe']);
    }

    public function test_user_cannot_get_statistics_for_other_users_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->getJson("/api/events/{$otherEvent->id}/guests/statistics");

        $response->assertForbidden();
    }

    public function test_collaborator_can_get_guest_statistics(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        Guest::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/guests/statistics");

        $response->assertOk()
            ->assertJsonPath('statistics.total', 5);
    }
}
