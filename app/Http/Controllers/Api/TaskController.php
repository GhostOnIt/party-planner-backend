<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Task;
use App\Services\PermissionService;
use App\Services\TaskBudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected TaskBudgetService $taskBudgetService
    ) {}

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
            'estimated_cost' => 'nullable|numeric|min:0',
            'budget_category' => 'nullable|string|in:location,catering,decoration,entertainment,photography,transportation,other',
            'assigned_to_user_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($event) {
                    if ($value) {
                        // Check if assigned user is event owner or active collaborator
                        if ($value !== $event->user_id) {
                            $isCollaborator = $event->collaborators()
                                ->where('user_id', $value)
                                ->whereNotNull('accepted_at')
                                ->exists();

                            if (!$isCollaborator) {
                                $fail('L\'utilisateur assigné doit être le propriétaire ou un collaborateur actif de l\'événement.');
                            }
                        }
                    }
                },
            ],
        ]);

        // Check assignment permission after validation
        if (!empty($validated['assigned_to_user_id'])) {
            $this->authorize('assign', Task::make(['event_id' => $event->id]));
        }

        $task = $event->tasks()->create($validated);

        // Synchronize budget item if task has a cost
        if ($this->taskBudgetService->shouldCreateBudgetItem($task)) {
            $this->taskBudgetService->syncBudgetItemFromTask($task);
        }

        $task->load('budgetItem');

        return response()->json($task, 201);
    }

    /**
     * Display the specified task.
     */
    public function show(Event $event, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load(['assignedUser', 'budgetItem']);

        return response()->json($task);
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, Event $event, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:todo,in_progress,completed,cancelled',
            'priority' => 'sometimes|required|in:low,medium,high',
            'due_date' => 'nullable|date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'budget_category' => 'nullable|string|in:location,catering,decoration,entertainment,photography,transportation,other',
            'assigned_to_user_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($event) {
                    if ($value) {
                        // Check if assigned user is event owner or active collaborator
                        if ($value !== $event->user_id) {
                            $isCollaborator = $event->collaborators()
                                ->where('user_id', $value)
                                ->whereNotNull('accepted_at')
                                ->exists();

                            if (!$isCollaborator) {
                                $fail('L\'utilisateur assigné doit être le propriétaire ou un collaborateur actif de l\'événement.');
                            }
                        }
                    }
                },
            ],
        ]);

        // If this is a status-only update, allow assigned users to change status without full tasks.edit permission.
        $keys = array_keys($validated);
        $isStatusOnly = count($keys) === 1 && $keys[0] === 'status';
        if ($isStatusOnly) {
            $this->authorize('updateStatus', $task);
        } else {
            $this->authorize('update', $task);
        }

        // Check assignment permission after validation
        if (isset($validated['assigned_to_user_id']) && $validated['assigned_to_user_id'] !== $task->assigned_to_user_id) {
            $this->authorize('assign', $task);
        }

        if (isset($validated['status'])) {
            if ($validated['status'] === 'completed' && $task->status !== 'completed') {
                $validated['completed_at'] = now();
            } elseif ($validated['status'] !== 'completed') {
                $validated['completed_at'] = null;
            }
        }

        // Track which attributes changed for budget sync
        $changedAttributes = [];
        foreach ($validated as $key => $value) {
            if ($task->getAttribute($key) != $value) {
                $changedAttributes[$key] = $value;
            }
        }

        $task->update($validated);

        // Always synchronize budget item when cost-related fields are present or changed
        // This handles: adding cost, removing cost, updating cost, or any cost-related change
        $costRelatedFields = ['estimated_cost', 'budget_category', 'title', 'description'];
        $hasCostRelatedChanges = !empty(array_intersect_key($changedAttributes, array_flip($costRelatedFields)));
        $hasCostInRequest = isset($validated['estimated_cost']) || isset($validated['budget_category']);
        
        // Sync if: cost-related fields changed OR cost fields are in the request
        if ($hasCostRelatedChanges || $hasCostInRequest) {
            $this->taskBudgetService->updateBudgetItemFromTask($task, $changedAttributes);
        }

        $task->load('budgetItem');

        return response()->json($task);
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Event $event, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        // Remove associated budget item if exists
        $this->taskBudgetService->removeBudgetItemFromTask($task);

        $task->delete();

        return response()->json(null, 204);
    }
}
