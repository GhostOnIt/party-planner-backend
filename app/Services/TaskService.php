<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Jobs\TaskReminderJob;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * Create a new task for an event.
     */
    public function create(Event $event, array $data): Task
    {
        $data['status'] = $data['status'] ?? TaskStatus::TODO->value;

        $task = $event->tasks()->create($data);

        // Send notification if assigned to someone other than event owner
        if (isset($data['assigned_to_user_id']) && $data['assigned_to_user_id'] !== $event->user_id) {
            $this->notifyAssignment($task);
        }

        return $task->fresh(['assignedUser']);
    }

    /**
     * Update a task.
     */
    public function update(Task $task, array $data): Task
    {
        $previousAssignee = $task->assigned_to_user_id;

        $task->update($data);

        // Notify new assignee if changed
        if (isset($data['assigned_to_user_id']) &&
            $data['assigned_to_user_id'] !== $previousAssignee &&
            $data['assigned_to_user_id'] !== $task->event->user_id) {
            $this->notifyAssignment($task);
        }

        return $task->fresh(['assignedUser']);
    }

    /**
     * Delete a task.
     */
    public function delete(Task $task): void
    {
        $task->delete();
    }

    /**
     * Assign a task to a user.
     */
    public function assign(Task $task, ?User $user): Task
    {
        $previousAssignee = $task->assigned_to_user_id;

        $task->update([
            'assigned_to_user_id' => $user?->id,
        ]);

        // Notify new assignee
        if ($user && $user->id !== $previousAssignee && $user->id !== $task->event->user_id) {
            $this->notifyAssignment($task);
        }

        return $task->fresh(['assignedUser']);
    }

    /**
     * Mark a task as completed.
     */
    public function complete(Task $task): Task
    {
        $task->markAsCompleted();

        // Notify event owner if completed by collaborator
        if ($task->assigned_to_user_id && $task->assigned_to_user_id !== $task->event->user_id) {
            $this->notifyCompletion($task);
        }

        return $task->fresh();
    }

    /**
     * Reopen a completed task.
     */
    public function reopen(Task $task): Task
    {
        $task->reopen();

        return $task->fresh();
    }

    /**
     * Update task status.
     */
    public function updateStatus(Task $task, TaskStatus $status): Task
    {
        if ($status === TaskStatus::COMPLETED) {
            return $this->complete($task);
        }

        $task->update(['status' => $status->value]);

        return $task->fresh();
    }

    /**
     * Update task priority.
     */
    public function updatePriority(Task $task, TaskPriority $priority): Task
    {
        $task->update(['priority' => $priority->value]);

        return $task->fresh();
    }

    /**
     * Apply tasks from a template to an event.
     */
    public function applyTemplateTask(Event $event, EventTemplate $template): Collection
    {
        $tasks = collect();

        if (empty($template->default_tasks)) {
            return $tasks;
        }

        DB::transaction(function () use ($event, $template, &$tasks) {
            foreach ($template->default_tasks as $taskData) {
                $task = $event->tasks()->create([
                    'title' => $taskData['title'] ?? $taskData,
                    'description' => $taskData['description'] ?? null,
                    'priority' => $taskData['priority'] ?? TaskPriority::MEDIUM->value,
                    'status' => TaskStatus::TODO->value,
                    'due_date' => $this->calculateDueDate($event, $taskData['days_before'] ?? null),
                ]);

                $tasks->push($task);
            }
        });

        return $tasks;
    }

    /**
     * Calculate due date based on event date.
     */
    protected function calculateDueDate(Event $event, ?int $daysBefore): ?\Carbon\Carbon
    {
        if (!$event->date || !$daysBefore) {
            return null;
        }

        return $event->date->copy()->subDays($daysBefore);
    }

    /**
     * Get task statistics for an event.
     */
    public function getStatistics(Event $event): array
    {
        $tasks = $event->tasks;

        return [
            'total' => $tasks->count(),
            'by_status' => [
                'todo' => $tasks->where('status', TaskStatus::TODO->value)->count(),
                'in_progress' => $tasks->where('status', TaskStatus::IN_PROGRESS->value)->count(),
                'completed' => $tasks->where('status', TaskStatus::COMPLETED->value)->count(),
                'cancelled' => $tasks->where('status', TaskStatus::CANCELLED->value)->count(),
            ],
            'by_priority' => [
                'high' => $tasks->where('priority', TaskPriority::HIGH->value)->count(),
                'medium' => $tasks->where('priority', TaskPriority::MEDIUM->value)->count(),
                'low' => $tasks->where('priority', TaskPriority::LOW->value)->count(),
            ],
            'overdue' => $tasks->filter(fn($t) => $t->isOverdue())->count(),
            'due_soon' => $tasks->filter(function ($task) {
                return $task->due_date &&
                    !$task->isCompleted() &&
                    $task->due_date->isBetween(now(), now()->addDays(3));
            })->count(),
            'unassigned' => $tasks->whereNull('assigned_to_user_id')->count(),
            'completion_rate' => $tasks->count() > 0
                ? round(($tasks->where('status', TaskStatus::COMPLETED->value)->count() / $tasks->count()) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get overdue tasks for an event.
     */
    public function getOverdueTasks(Event $event): Collection
    {
        return $event->tasks()
            ->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get tasks due soon.
     */
    public function getTasksDueSoon(Event $event, int $days = 3): Collection
    {
        return $event->tasks()
            ->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays($days)])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Send task assignment notification.
     */
    protected function notifyAssignment(Task $task): void
    {
        if (!$task->assigned_to_user_id) {
            return;
        }

        Notification::create([
            'user_id' => $task->assigned_to_user_id,
            'event_id' => $task->event_id,
            'type' => 'task_reminder',
            'title' => 'Nouvelle tâche assignée',
            'message' => "La tâche \"{$task->title}\" vous a été assignée pour l'événement \"{$task->event->title}\".",
            'sent_via' => 'database',
        ]);
    }

    /**
     * Send task completion notification to event owner.
     */
    protected function notifyCompletion(Task $task): void
    {
        Notification::create([
            'user_id' => $task->event->user_id,
            'event_id' => $task->event_id,
            'type' => 'task_reminder',
            'title' => 'Tâche terminée',
            'message' => "La tâche \"{$task->title}\" a été marquée comme terminée.",
            'sent_via' => 'database',
        ]);
    }

    /**
     * Schedule reminders for tasks due soon.
     */
    public function scheduleReminders(Event $event): int
    {
        $reminderDays = config('partyplanner.notifications.reminders.task_days_before', [3, 1]);
        $count = 0;

        foreach ($reminderDays as $days) {
            $tasks = $event->tasks()
                ->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value])
                ->whereNotNull('due_date')
                ->whereNotNull('assigned_to_user_id')
                ->whereDate('due_date', now()->addDays($days))
                ->get();

            foreach ($tasks as $task) {
                TaskReminderJob::dispatch($task, $days);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Bulk update task status.
     */
    public function bulkUpdateStatus(array $taskIds, TaskStatus $status): int
    {
        $data = ['status' => $status->value];

        if ($status === TaskStatus::COMPLETED) {
            $data['completed_at'] = now();
        }

        return Task::whereIn('id', $taskIds)->update($data);
    }

    /**
     * Reorder tasks (for drag-and-drop).
     */
    public function reorder(array $taskOrder): void
    {
        DB::transaction(function () use ($taskOrder) {
            foreach ($taskOrder as $index => $taskId) {
                Task::where('id', $taskId)->update(['sort_order' => $index]);
            }
        });
    }

    /**
     * Duplicate a task.
     */
    public function duplicate(Task $task): Task
    {
        return $task->event->tasks()->create([
            'title' => $task->title . ' (copie)',
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => TaskStatus::TODO->value,
            'due_date' => null,
            'assigned_to_user_id' => null,
        ]);
    }

    /**
     * Get assignable users for an event (owner + collaborators).
     */
    public function getAssignableUsers(Event $event): Collection
    {
        $users = collect([$event->user]);

        $collaboratorUsers = $event->collaborators()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        return $users->merge($collaboratorUsers)->unique('id');
    }
}
