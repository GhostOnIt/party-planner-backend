<?php

namespace Tests\Unit\Models;

use App\Models\Collaborator;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $event->user);
        $this->assertEquals($user->id, $event->user->id);
    }

    public function test_owner_can_view_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($event->canBeViewedBy($user));
    }

    public function test_collaborator_can_view_event(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();

        $event = Event::factory()->create(['user_id' => $owner->id]);
        Collaborator::factory()->create([
            'event_id' => $event->id,
            'user_id' => $collaborator->id,
        ]);

        $this->assertTrue($event->canBeViewedBy($collaborator));
    }

    public function test_stranger_cannot_view_event(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($event->canBeViewedBy($stranger));
    }

    public function test_owner_can_edit_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($event->canBeEditedBy($user));
    }

    public function test_editor_collaborator_can_edit_event(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();

        $event = Event::factory()->create(['user_id' => $owner->id]);
        Collaborator::factory()->create([
            'event_id' => $event->id,
            'user_id' => $editor->id,
            'role' => 'editor',
        ]);

        $this->assertTrue($event->canBeEditedBy($editor));
    }

    public function test_viewer_collaborator_cannot_edit_event(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $event = Event::factory()->create(['user_id' => $owner->id]);
        Collaborator::factory()->create([
            'event_id' => $event->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
        ]);

        $this->assertFalse($event->canBeEditedBy($viewer));
    }

    public function test_event_has_guests_relationship(): void
    {
        $event = Event::factory()->create();

        $this->assertNotNull($event->guests);
    }

    public function test_event_has_tasks_relationship(): void
    {
        $event = Event::factory()->create();

        $this->assertNotNull($event->tasks);
    }

    public function test_event_has_budget_items_relationship(): void
    {
        $event = Event::factory()->create();

        $this->assertNotNull($event->budgetItems);
    }

    public function test_event_has_photos_relationship(): void
    {
        $event = Event::factory()->create();

        $this->assertNotNull($event->photos);
    }

    public function test_event_has_collaborators_relationship(): void
    {
        $event = Event::factory()->create();

        $this->assertNotNull($event->collaborators);
    }
}
