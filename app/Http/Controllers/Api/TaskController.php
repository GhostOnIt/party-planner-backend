<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks for an event.
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewAny', [Task::class, $event]);

        $query = $event->tasks()->with('assignedUser');

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by priority
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Filter by assigned user
        if ($assignedTo = $request->input('assigned_to')) {
            $query->where('assigned_to_user_id', $assignedTo);
        }

        // Search by title
        if ($search = $request->input('search')) {
            $query->where('title', 'ilike', "%{$search}%");
        }

        $tasks = $query->orderBy('due_date')->get();

        return response()->json($tasks);
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('create', [Task::class, $event]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'due_date' => 'nullable|date',
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        $task = $event->tasks()->create($validated);

        return response()->json($task, 201);
    }

    /**
     * Display the specified task.
     */
    public function show(Event $event, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load('assignedUser');

        return response()->json($task);
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, Event $event, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:todo,in_progress,completed,cancelled',
            'priority' => 'sometimes|required|in:low,medium,high',
            'due_date' => 'nullable|date',
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        if (isset($validated['status'])) {
            if ($validated['status'] === 'completed' && $task->status !== 'completed') {
                $validated['completed_at'] = now();
            } elseif ($validated['status'] !== 'completed') {
                $validated['completed_at'] = null;
            }
        }

        $task->update($validated);

        return response()->json($task);
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Event $event, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }
}
