<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    /**
     * Display a listing of tasks for an event.
     */
    public function index(Request $request, Event $event): View
    {
        $this->authorize('view', $event);

        $query = $event->tasks()->with('assignedUser');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by assignee
        if ($request->filled('assignee')) {
            if ($request->assignee === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            } else {
                $query->where('assigned_to_user_id', $request->assignee);
            }
        }

        // Filter overdue
        if ($request->boolean('overdue')) {
            $query->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value])
                ->whereNotNull('due_date')
                ->where('due_date', '<', now());
        }

        // Search
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Sorting - PostgreSQL compatible
        $tasks = $query
            ->orderByRaw("CASE status
                WHEN 'in_progress' THEN 1
                WHEN 'todo' THEN 2
                WHEN 'completed' THEN 3
                WHEN 'cancelled' THEN 4
                ELSE 5 END")
            ->orderByRaw("CASE priority
                WHEN 'high' THEN 1
                WHEN 'medium' THEN 2
                WHEN 'low' THEN 3
                ELSE 4 END")
            ->orderBy('due_date')
            ->get();

        $stats = $this->taskService->getStatistics($event);
        $assignableUsers = $this->taskService->getAssignableUsers($event);
        $taskStatuses = TaskStatus::options();
        $taskPriorities = TaskPriority::options();

        return view('events.tasks.index', compact(
            'event',
            'tasks',
            'stats',
            'assignableUsers',
            'taskStatuses',
            'taskPriorities'
        ));
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $this->taskService->create($event, $request->validated());

        return redirect()
            ->route('events.tasks.index', $event)
            ->with('success', 'Tâche créée avec succès.');
    }

    /**
     * Show task details (for modal or separate page).
     */
    public function show(Event $event, Task $task): View
    {
        $this->authorize('view', $event);

        $task->load('assignedUser');
        $assignableUsers = $this->taskService->getAssignableUsers($event);

        return view('events.tasks.show', compact('event', 'task', 'assignableUsers'));
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Event $event, Task $task): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $this->taskService->update($task, $request->validated());

        return redirect()
            ->route('events.tasks.index', $event)
            ->with('success', 'Tâche mise à jour avec succès.');
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Event $event, Task $task): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $this->taskService->delete($task);

        return redirect()
            ->route('events.tasks.index', $event)
            ->with('success', 'Tâche supprimée avec succès.');
    }

    /**
     * Assign task to a user.
     */
    public function assign(Request $request, Event $event, Task $task): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $validated = $request->validate([
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        $user = $validated['assigned_to_user_id']
            ? User::find($validated['assigned_to_user_id'])
            : null;

        $this->taskService->assign($task, $user);

        $message = $user
            ? "Tâche assignée à {$user->name}."
            : 'Assignation de la tâche retirée.';

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Mark task as completed.
     */
    public function complete(Event $event, Task $task): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $this->taskService->complete($task);

        return redirect()
            ->back()
            ->with('success', 'Tâche marquée comme terminée.');
    }

    /**
     * Reopen a completed task.
     */
    public function reopen(Event $event, Task $task): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $this->taskService->reopen($task);

        return redirect()
            ->back()
            ->with('success', 'Tâche réouverte.');
    }

    /**
     * Update task status.
     */
    public function updateStatus(Request $request, Event $event, Task $task): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', TaskStatus::values())],
        ]);

        $status = TaskStatus::from($validated['status']);
        $this->taskService->updateStatus($task, $status);

        return redirect()
            ->back()
            ->with('success', 'Statut de la tâche mis à jour.');
    }

    /**
     * Update task priority.
     */
    public function updatePriority(Request $request, Event $event, Task $task): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $validated = $request->validate([
            'priority' => ['required', 'string', 'in:' . implode(',', TaskPriority::values())],
        ]);

        $priority = TaskPriority::from($validated['priority']);
        $this->taskService->updatePriority($task, $priority);

        return redirect()
            ->back()
            ->with('success', 'Priorité de la tâche mise à jour.');
    }

    /**
     * Duplicate a task.
     */
    public function duplicate(Event $event, Task $task): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $newTask = $this->taskService->duplicate($task);

        return redirect()
            ->route('events.tasks.index', $event)
            ->with('success', "Tâche dupliquée : {$newTask->title}");
    }

    /**
     * Bulk complete tasks.
     */
    public function bulkComplete(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $validated = $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
        ]);

        // Verify tasks belong to event
        $taskIds = Task::whereIn('id', $validated['task_ids'])
            ->where('event_id', $event->id)
            ->pluck('id')
            ->toArray();

        $count = $this->taskService->bulkUpdateStatus($taskIds, TaskStatus::COMPLETED);

        return redirect()
            ->back()
            ->with('success', "{$count} tâche(s) marquée(s) comme terminée(s).");
    }

    /**
     * Bulk delete tasks.
     */
    public function bulkDelete(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageTasks', $event);

        $validated = $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
        ]);

        $count = Task::whereIn('id', $validated['task_ids'])
            ->where('event_id', $event->id)
            ->delete();

        return redirect()
            ->back()
            ->with('success', "{$count} tâche(s) supprimée(s).");
    }
}
