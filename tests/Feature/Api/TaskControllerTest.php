<?php

namespace Tests\Feature\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Collaborator;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskControllerTest extends TestCase
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

    public function test_user_can_list_tasks_for_their_event(): void
    {
        Sanctum::actingAs($this->user);

        Task::factory()->count(5)->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/tasks");

        $response->assertOk()
            ->assertJsonCount(5); // Tasks are returned as a direct array
    }

    public function test_user_cannot_list_tasks_for_other_users_event(): void
    {
        Sanctum::actingAs($this->user);

        $otherEvent = Event::factory()->create();

        $response = $this->getJson("/api/events/{$otherEvent->id}/tasks");

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Store Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_create_task(): void
    {
        Sanctum::actingAs($this->user);

        $taskData = [
            'title' => 'Réserver le DJ',
            'description' => 'Contacter plusieurs DJ pour comparer les prix',
            'priority' => TaskPriority::HIGH->value,
            'due_date' => now()->addWeek()->format('Y-m-d'),
        ];

        $response = $this->postJson("/api/events/{$this->event->id}/tasks", $taskData);

        $response->assertCreated()
            ->assertJsonFragment(['title' => 'Réserver le DJ']);

        $this->assertDatabaseHas('tasks', [
            'event_id' => $this->event->id,
            'title' => 'Réserver le DJ',
        ]);
    }

    public function test_create_task_requires_title(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/tasks", [
            'priority' => TaskPriority::MEDIUM->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_task_requires_valid_priority(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/events/{$this->event->id}/tasks", [
            'title' => 'Test Task',
            'priority' => 'invalid_priority',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_task_can_be_assigned_to_user(): void
    {
        Sanctum::actingAs($this->user);

        $assignee = User::factory()->create();
        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $assignee->id,
            'role' => 'editor',
        ]);

        $response = $this->postJson("/api/events/{$this->event->id}/tasks", [
            'title' => 'Assigned Task',
            'priority' => TaskPriority::MEDIUM->value,
            'assigned_to_user_id' => $assignee->id,
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['assigned_to_user_id' => $assignee->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_view_task_details(): void
    {
        Sanctum::actingAs($this->user);

        $task = Task::factory()->create(['event_id' => $this->event->id]);

        $response = $this->getJson("/api/events/{$this->event->id}/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $task->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_update_task(): void
    {
        Sanctum::actingAs($this->user);

        $task = Task::factory()->todo()->create(['event_id' => $this->event->id]);

        $response = $this->putJson("/api/events/{$this->event->id}/tasks/{$task->id}", [
            'title' => 'Updated Task Title',
            'priority' => TaskPriority::HIGH->value,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Updated Task Title']);
    }

    public function test_user_can_mark_task_as_completed(): void
    {
        Sanctum::actingAs($this->user);

        $task = Task::factory()->todo()->create(['event_id' => $this->event->id]);

        $response = $this->putJson("/api/events/{$this->event->id}/tasks/{$task->id}", [
            'title' => $task->title,
            'priority' => $task->priority,
            'status' => TaskStatus::COMPLETED->value,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => TaskStatus::COMPLETED->value]);
    }

    public function test_editor_collaborator_can_update_task(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'editor',
        ]);

        $task = Task::factory()->create(['event_id' => $this->event->id]);

        $response = $this->putJson("/api/events/{$this->event->id}/tasks/{$task->id}", [
            'title' => 'Collaborator Update',
            'priority' => TaskPriority::LOW->value,
        ]);

        $response->assertOk();
    }

    public function test_viewer_cannot_update_task(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        $task = Task::factory()->create(['event_id' => $this->event->id]);

        $response = $this->putJson("/api/events/{$this->event->id}/tasks/{$task->id}", [
            'title' => 'Should Fail',
            'priority' => TaskPriority::LOW->value,
        ]);

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tests
    |--------------------------------------------------------------------------
    */

    public function test_user_can_delete_task(): void
    {
        Sanctum::actingAs($this->user);

        $task = Task::factory()->create(['event_id' => $this->event->id]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/tasks/{$task->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_viewer_cannot_delete_task(): void
    {
        $collaborator = User::factory()->create();
        Sanctum::actingAs($collaborator);

        Collaborator::factory()->create([
            'event_id' => $this->event->id,
            'user_id' => $collaborator->id,
            'role' => 'viewer',
        ]);

        $task = Task::factory()->create(['event_id' => $this->event->id]);

        $response = $this->deleteJson("/api/events/{$this->event->id}/tasks/{$task->id}");

        $response->assertForbidden();
    }
}
