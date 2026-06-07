<?php

namespace Tests\Feature\Api;

use App\Models\Collaborator;
use App\Models\CollaborationInvitation;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollaboratorControllerTest extends TestCase
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

    public function test_owner_can_list_collaborators(): void
    {
        Sanctum::actingAs($this->user);

        $collaborators = User::factory()->count(3)->create();
        foreach ($collaborators as $collaborator) {
            Collaborator::factory()->create([
                'event_id' => $this->event->id,
                'user_id' => $collaborator->id,
            ]);
        }

        $response = $this->getJson("/api/events/{$this->event->id}/collaborators");

        $response->assertOk()
            ->assertJsonCount(3, 'collaborators'); // API returns 'collaborators' key
    }

    public function test_collaborator_can_list_other_collaborators(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/collaborators");

        $response->assertOk();
    }

    public function test_non_collaborator_cannot_list_collaborators(): void
    {
        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $response = $this->getJson("/api/events/{$this->event->id}/collaborators");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Statistics Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_get_collaborator_statistics(): void
    {
        Sanctum::actingAs($this->user);

        Collaborator::factory()->count(2)->create([
            'event_id' => $this->event->id,
            'role' => 'editor',
        ]);
        Collaborator::factory()->count(3)->create([
            'event_id' => $this->event->id,
            'role' => 'viewer',
        ]);

        $response = $this->getJson("/api/events/{$this->event->id}/collaborators/statistics");

        $response->assertOk()
            ->assertJsonStructure([
                'stats',
                'can_add_collaborator',
                'remaining_slots',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store (Invite) Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_invite_collaborator(): void
    {
        Sanctum::actingAs($this->user);

        $newCollaborator = User::factory()->create();

        $response = $this->postJson("/api/events/{$this->event->id}/collaborators", [
            'email' => $newCollaborator->email,
            'role' => 'editor',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $newCollaborator->id,
            'role' => 'editor',
        ]);
    }

    public function test_invite_requires_valid_email(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/collaborators", [
            'email' => 'invalid-email',
            'role' => 'editor',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invite_accepts_non_existing_user_creates_pending_invitation(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/collaborators", [
            'email' => 'nonexistent@example.com',
            'role' => 'editor',
        ]);

        $response->assertCreated()
            ->assertJsonPath('pending', true)
            ->assertJsonPath('pending_invitation.email', 'nonexistent@example.com')
            ->assertJsonPath('pending_invitation.event_id', $this->event->id);
    }

    public function test_invite_requires_valid_role(): void
    {
        Sanctum::actingAs($this->user);

        $newCollaborator = User::factory()->create();

        $response = $this->postJson("/api/events/{$this->event->id}/collaborators", [
            'email' => $newCollaborator->email,
            'role' => 'owner', // Cannot invite as owner
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_collaborator_without_permission_cannot_invite_collaborator(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        $newUser = User::factory()->create();

        $response = $this->postJson("/api/events/{$this->event->id}/collaborators", [
            'email' => $newUser->email,
            'role' => 'viewer',
        ]);

        $response->assertForbidden();
    }

    public function test_coordinator_can_invite_collaborator(): void
    {
        $coordinator = User::factory()->create();
        Sanctum::actingAs($coordinator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $coordinator->id,
            'role' => 'coordinator',
            'accepted_at' => now(),
        ]);

        $newUser = User::factory()->create();

        $response = $this->postJson("/api/events/{$this->event->id}/collaborators", [
            'email' => $newUser->email,
            'role' => 'viewer',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $newUser->id,
            'role' => 'viewer',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_update_collaborator_role(): void
    {
        Sanctum::actingAs($this->user);

        $collaboratorUser = User::factory()->create();
        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaboratorUser->id,
            'role' => 'viewer',
        ]);

        $response = $this->putJson("/api/events/{$this->event->id}/collaborators/{$collaboratorUser->id}", [
            'role' => 'editor',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $collaboratorUser->id,
            'role' => 'editor',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tests
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_remove_collaborator(): void
    {
        Sanctum::actingAs($this->user);

        $collaboratorUser = User::factory()->create();
        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaboratorUser->id,
        ]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/collaborators/{$collaboratorUser->id}");

        $response->assertOk(); // API returns 200 with message, not 204

        $this->assertDatabaseMissing('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $collaboratorUser->id,
        ]);
    }

    public function test_collaborator_without_permission_cannot_remove_other_collaborator(): void
    {
        $collaborator1 = User::factory()->create();
        $collaborator2 = User::factory()->create();

        Sanctum::actingAs($collaborator1);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator1->id,
            'role' => 'viewer',
        ]);
        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator2->id,
            'role' => 'viewer',
        ]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/collaborators/{$collaborator2->id}");

        $response->assertForbidden();
    }

    public function test_coordinator_can_remove_other_collaborator(): void
    {
        $coordinator = User::factory()->create();
        $collaborator = User::factory()->create();

        Sanctum::actingAs($coordinator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $coordinator->id,
            'role' => 'coordinator',
            'accepted_at' => now(),
        ]);
        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
            'accepted_at' => now(),
        ]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/collaborators/{$collaborator->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Accept/Decline Tests
    |--------------------------------------------------------------------------
    */

    public function test_collaborator_can_accept_invitation(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'accepted_at' => null,
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/collaborators/accept");

        $response->assertOk();

        $this->assertDatabaseHas('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
        ]);

        $collab = Collaborator::where('event_id', $this->event->id)
            ->where('user_id', $collaborator->id)
            ->first();

        $this->assertNotNull($collab->accepted_at);
    }

    public function test_collaborator_can_decline_invitation(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'accepted_at' => null,
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/collaborators/decline");

        $response->assertOk();

        $this->assertDatabaseMissing('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Leave Tests
    |--------------------------------------------------------------------------
    */

    public function test_collaborator_can_leave_event(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'accepted_at' => now(),
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/collaborators/leave");

        $response->assertOk();

        $this->assertDatabaseMissing('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | My Collaborations Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_list_their_collaborations(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        // Create accepted collaborations for this user
        $events = Event::factory()->count(3)->create();
        foreach ($events as $event) {
            Collaborator::factory()->create([
                'event_id' => $event->id,
                'user_id' => $collaborator->id,
                'accepted_at' => now(), // Must be accepted to show in collaborations
            ]);
        }

        $response = $this->getJson('/api/collaborations');

        $response->assertOk()
            ->assertJsonCount(3, 'collaborations'); // API returns 'collaborations' key
    }

    public function test_user_can_list_pending_invitations(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        // Create pending collaborations
        $events = Event::factory()->count(2)->create();
        foreach ($events as $event) {
            Collaborator::factory()->create([
                'event_id' => $event->id,
                'user_id' => $collaborator->id,
                'accepted_at' => null,
            ]);
        }

        // Create accepted collaboration
        $acceptedEvent = Event::factory()->create();
        Collaborator::factory()->create([
            'event_id' => $acceptedEvent->id,
            'user_id' => $collaborator->id,
            'accepted_at' => now(),
        ]);

        $response = $this->getJson('/api/collaborations/pending');

        $response->assertOk()
            ->assertJsonCount(2, 'invitations'); // API returns 'invitations' key
    }

    public function test_pending_invitation_count_is_available(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->count(2)->create([
            'user_id' => $collaborator->id,
            'accepted_at' => null,
        ]);

        $response = $this->getJson('/api/invitations/pending-count');

        $response->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_invitation_token_rejects_wrong_account(): void
    {
        $expectedUser = User::factory()->create();
        $wrongUser = User::factory()->create();
        Sanctum::actingAs($wrongUser);

        $invitation = CollaborationInvitation::create([
            'event_id' => $this->event->id,
            'email' => $expectedUser->email,
            'roles' => ['viewer'],
            'token' => 'token-wrong-account',
            'invited_at' => now(),
        ]);

        $response = $this->getJson("/api/invitations/by-token/{$invitation->token}");

        $response->assertForbidden()
            ->assertJsonPath('expected_email', $expectedUser->email);
    }

    public function test_user_can_accept_invitation_by_token(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        $invitation = CollaborationInvitation::create([
            'event_id' => $this->event->id,
            'email' => $collaborator->email,
            'roles' => ['viewer'],
            'token' => 'token-accept',
            'invited_at' => now(),
        ]);

        $response = $this->postJson("/api/invitations/by-token/{$invitation->token}/accept");

        $response->assertOk();
        $this->assertDatabaseHas('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
        ]);

        $created = Collaborator::where('event_id', $this->event->id)
            ->where('user_id', $collaborator->id)
            ->first();
        $this->assertNotNull($created?->accepted_at);
        $this->assertDatabaseMissing('collaboration_invitations', ['id' => $invitation->id]);
    }

    public function test_user_can_leave_collaboration_by_uuid_event_id(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'accepted_at' => now(),
        ]);

        $response = $this->deleteJson("/api/user/collaborations/{$this->event->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('collaborators', [
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
        ]);
    }
}
