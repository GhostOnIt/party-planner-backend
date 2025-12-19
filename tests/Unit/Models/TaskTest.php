<?php

namespace Tests\Unit\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_belongs_to_event(): void
    {
        $event = Event::factory()->create();
        $task = Task::factory()->create(['event_id' => $event->id]);

        $this->assertInstanceOf(Event::class, $task->event);
        $this->assertEquals($event->id, $task->event->id);
    }

    public function test_task_can_be_assigned_to_user(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['assigned_to_user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $task->assignedUser);
        $this->assertEquals($user->id, $task->assignedUser->id);
    }

    public function test_task_is_completed(): void
    {
        $task = Task::factory()->completed()->create();

        $this->assertTrue($task->isCompleted());
    }

    public function test_task_is_not_completed(): void
    {
        $task = Task::factory()->todo()->create();

        $this->assertFalse($task->isCompleted());
    }

    public function test_task_is_overdue(): void
    {
        $task = Task::factory()->overdue()->create();

        $this->assertTrue($task->isOverdue());
    }

    public function test_task_is_not_overdue_when_completed(): void
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::COMPLETED->value,
            'due_date' => now()->subDay(),
            'completed_at' => now(),
        ]);

        $this->assertFalse($task->isOverdue());
    }

    public function test_task_is_high_priority(): void
    {
        $task = Task::factory()->highPriority()->create();

        $this->assertTrue($task->isHighPriority());
    }

    public function test_task_can_be_marked_as_completed(): void
    {
        $task = Task::factory()->todo()->create();

        $task->markAsCompleted();

        $this->assertEquals(TaskStatus::COMPLETED->value, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_task_can_be_reopened(): void
    {
        $task = Task::factory()->completed()->create();

        $task->reopen();

        $this->assertEquals(TaskStatus::TODO->value, $task->status);
        $this->assertNull($task->completed_at);
    }
}
