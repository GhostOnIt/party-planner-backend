<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\User;
use App\Services\AdminActivityService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected AdminActivityService $activityService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | User Dashboard Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get user dashboard statistics.
     */
    public function userStats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getUserPersonalStats($request->user());

        return response()->json([
            'stats' => $stats,
        ]);
    }

    /**
     * Get chart data for user dashboard.
     */
    public function chartData(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', 'month');

        // For admin, return global chart data
        if ($user->isAdmin()) {
            $chartData = $this->dashboardService->getChartData($period);
        } else {
            // For regular users, return their events data
            $chartData = $this->getUserChartData($user, $period);
        }

        return response()->json([
            'chart_data' => $chartData,
            'period' => $period,
        ]);
    }

    /**
     * Get event dashboard data.
     */
    public function eventDashboardData(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $dashboardData = $this->dashboardService->getEventDashboard($event);

        return response()->json($dashboardData);
    }

    /**
     * Get urgent tasks (due within 7 days) for all user's events.
     */
    public function urgentTasks(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all event IDs the user owns or collaborates on
        $ownedEventIds = $user->events()->pluck('id');
        $collaboratingEventIds = $user->collaborations()
            ->whereNotNull('accepted_at')
            ->pluck('event_id');

        $eventIds = $ownedEventIds->merge($collaboratingEventIds)->unique();

        // Get urgent tasks grouped by event
        $urgentTasks = Task::with(['event:id,title,date', 'assignedUser:id,name'])
            ->whereIn('event_id', $eventIds)
            ->urgent(7)
            ->get()
            ->groupBy('event_id')
            ->map(function ($tasks, $eventId) {
                $event = $tasks->first()->event;
                return [
                    'event' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'date' => $event->date,
                    ],
                    'tasks' => $tasks->map(fn($task) => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => $task->priority,
                        'status' => $task->status,
                        'due_date' => $task->due_date,
                        'is_overdue' => $task->isOverdue(),
                        'days_until_due' => $task->due_date ? now()->diffInDays($task->due_date, false) : null,
                        'assigned_user' => $task->assignedUser ? [
                            'id' => $task->assignedUser->id,
                            'name' => $task->assignedUser->name,
                        ] : null,
                    ])->values(),
                    'count' => $tasks->count(),
                ];
            })
            ->values();

        $totalUrgent = $urgentTasks->sum('count');
        $overdueCount = Task::whereIn('event_id', $eventIds)
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        return response()->json([
            'data' => $urgentTasks,
            'summary' => [
                'total_urgent' => $totalUrgent,
                'total_overdue' => $overdueCount,
                'events_with_urgent_tasks' => $urgentTasks->count(),
            ],
        ]);
    }

    /**
     * Get user-specific chart data.
     */
    protected function getUserChartData(User $user, string $period): array
    {
        $events = $user->events;

        return [
            'guests_by_event' => $events->map(fn($event) => [
                'event' => $event->title,
                'total' => $event->guests()->count(),
                'confirmed' => $event->guests()->where('rsvp_status', 'accepted')->count(),
            ])->toArray(),
            'tasks_by_status' => [
                'todo' => $events->sum(fn($e) => $e->tasks()->where('status', 'todo')->count()),
                'in_progress' => $events->sum(fn($e) => $e->tasks()->where('status', 'in_progress')->count()),
                'completed' => $events->sum(fn($e) => $e->tasks()->where('status', 'completed')->count()),
            ],
            'budget_by_event' => $events->map(fn($event) => [
                'event' => $event->title,
                'estimated' => $event->budgetItems()->sum('estimated_cost'),
                'actual' => $event->budgetItems()->sum('actual_cost'),
            ])->toArray(),
        ];
    }

    /**
     * Get dashboard statistics with filters (period + event type).
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', '7days');
        $eventType = $request->input('type', 'all');
        $customRange = null;

        if ($period === 'custom') {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            if ($startDate && $endDate) {
                $customRange = [
                    'start' => \Carbon\Carbon::parse($startDate),
                    'end' => \Carbon\Carbon::parse($endDate),
                ];
            }
        }

        $stats = $this->dashboardService->getUserStatsWithFilters($user, $period, $eventType, $customRange);

        return response()->json($stats);
    }

    /**
     * Get confirmations chart data with filters.
     */
    public function confirmations(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', '7days');
        $eventType = $request->input('type', 'all');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 5);
        $search = $request->input('search', '');
        $sortBy = $request->input('sort_by', 'confirmRate');
        $sortOrder = $request->input('sort_order', 'desc');

        $filters = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ];

        $data = $this->dashboardService->getConfirmationsData($user, $period, $eventType, $filters);

        return response()->json($data);
    }

    /**
     * Get events by type chart data.
     */
    public function eventsByType(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->input('period', '7days');
        $eventType = $request->input('type', 'all');

        $data = $this->dashboardService->getEventsByTypeData($user, $period, $eventType);

        return response()->json($data);
    }

    /**
     * Get upcoming events.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int) $request->input('limit', 4);

        // Get all event IDs the user owns or collaborates on
        $ownedEventIds = $user->events()->pluck('id');
        $collaboratingEventIds = $user->collaborations()
            ->whereNotNull('accepted_at')
            ->pluck('event_id');

        $eventIds = $ownedEventIds->merge($collaboratingEventIds)->unique();

        $events = Event::whereIn('id', $eventIds)
            ->where('date', '>=', now()->startOfDay())
            ->orderBy('date', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->title,
                    'type' => $event->type,
                    'date' => $event->date ? $event->date->format('d M Y') : null,
                    'location' => $event->location,
                ];
            });

        return response()->json($events);
    }

    /**
     * Get recent activity.
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int) $request->input('limit', 6);

        $activities = $this->dashboardService->getUserRecentActivity($user, $limit);

        return response()->json($activities);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get admin dashboard statistics.
     */
    public function adminStats(): JsonResponse
    {
        $stats = $this->dashboardService->getAdminStats();

        return response()->json([
            'stats' => $stats,
        ]);
    }

    /**
     * Get admin chart data.
     */
    public function adminChartData(Request $request): JsonResponse
    {
        $period = $request->input('period', 'month');
        $chartData = $this->dashboardService->getChartData($period);

        return response()->json([
            'chart_data' => $chartData,
            'period' => $period,
        ]);
    }

    /**
     * Get all users for admin.
     */
    public function adminUsers(Request $request): JsonResponse
    {
        $query = User::query();

        // Search (case-insensitive)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Filter by role
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $users = $query->withCount(['events', 'collaborations'])
            ->paginate($request->input('per_page', 15));

        return response()->json($users);
    }

    /**
     * Get a specific user for admin.
     */
    public function adminUserShow(User $user): JsonResponse
    {
        $user->load(['events', 'collaborations']);
        $user->loadCount(['events', 'collaborations']);

        $stats = $this->dashboardService->getUserPersonalStats($user);

        return response()->json([
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    /**
     * Update user role (admin only).
     */
    public function adminUserUpdateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,user'],
        ]);

        // Prevent self-demotion
        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return response()->json([
                'message' => 'Vous ne pouvez pas modifier votre propre rôle.',
            ], 422);
        }

        $oldRole = $user->role;
        $user->update(['role' => $validated['role']]);

        // Log the role change
        $this->activityService->logUserAction('update_role', $user, [
            'old' => ['role' => $oldRole instanceof \BackedEnum ? $oldRole->value : $oldRole],
            'new' => ['role' => $validated['role']],
        ]);

        return response()->json([
            'message' => 'Rôle mis à jour.',
            'user' => $user,
        ]);
    }

    /**
     * Delete a user (admin only).
     */
    public function adminUserDestroy(Request $request, User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        // Prevent deleting other admins
        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Impossible de supprimer un autre administrateur.',
            ], 422);
        }

        // Log before deletion
        $this->activityService->logUserAction('delete', $user, [
            'old' => $user->only(['id', 'name', 'email', 'role']),
            'new' => null,
        ]);

        $user->delete();

        return response()->json([
            'message' => 'Utilisateur supprimé.',
        ]);
    }

    /**
     * Update a user (admin only).
     */
    public function adminUserUpdate(Request $request, User $user): JsonResponse
    {
        // Prevent modifying other admins (unless self)
        if ($user->isAdmin() && $user->id !== $request->user()->id) {
            return response()->json([
                'message' => 'Impossible de modifier un autre administrateur.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:admin,user',
            'is_active' => 'sometimes|boolean',
        ]);

        // Prevent self-demotion
        if ($user->id === $request->user()->id && isset($validated['role']) && $validated['role'] !== 'admin') {
            return response()->json([
                'message' => 'Vous ne pouvez pas modifier votre propre rôle.',
            ], 422);
        }

        // Prevent self-deactivation
        if ($user->id === $request->user()->id && isset($validated['is_active']) && !$validated['is_active']) {
            return response()->json([
                'message' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ], 422);
        }

        $oldData = $user->only(['name', 'email', 'phone', 'role', 'is_active']);
        $user->update($validated);

        $this->activityService->logUserAction('update', $user, [
            'old' => $oldData,
            'new' => $user->only(['name', 'email', 'phone', 'role', 'is_active']),
        ]);

        return response()->json([
            'message' => 'Utilisateur mis à jour.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Toggle user active status (admin only).
     */
    public function adminUserToggleActive(Request $request, User $user): JsonResponse
    {
        // Prevent self-deactivation
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas modifier le statut de votre propre compte.',
            ], 422);
        }

        // Prevent toggling other admins
        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Impossible de modifier le statut d\'un autre administrateur.',
            ], 422);
        }

        $oldStatus = $user->is_active;
        $newStatus = $user->toggleActive();

        $this->activityService->logUserAction('toggle_active', $user, [
            'old' => ['is_active' => $oldStatus],
            'new' => ['is_active' => $newStatus],
        ]);

        return response()->json([
            'message' => $newStatus ? 'Compte activé.' : 'Compte désactivé.',
            'user' => $user,
            'is_active' => $newStatus,
        ]);
    }

    /**
     * Get all events for admin.
     */
    public function adminEvents(Request $request): JsonResponse
    {
        $query = Event::with('user');

        // Search (case-insensitive)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                  });
            });
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $events = $query->withCount(['guests', 'tasks', 'budgetItems'])
            ->paginate($request->input('per_page', 15));

        return response()->json($events);
    }

    /**
     * Delete an event (admin only).
     */
    public function adminEventDestroy(Request $request, Event $event): JsonResponse
    {
        // Log before deletion
        $this->activityService->logUserAction('delete', $event, [
            'old' => $event->only(['id', 'title', 'type', 'user_id']),
            'new' => null,
        ]);

        $event->delete();

        return response()->json([
            'message' => 'Événement supprimé.',
        ]);
    }

    /**
     * Get all payments for admin.
     */
    public function adminPayments(Request $request): JsonResponse
    {
        $query = Payment::with(['subscription.event.user']);

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by method
        if ($method = $request->input('method')) {
            $query->where('payment_method', $method);
        }

        // Date range
        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $payments = $query->paginate($request->input('per_page', 15));

        return response()->json($payments);
    }

    /**
     * Refund a payment (admin only).
     */
    public function adminPaymentRefund(Request $request, Payment $payment): JsonResponse
    {
        if (!$payment->canBeRefunded()) {
            return response()->json([
                'message' => 'Ce paiement ne peut pas être remboursé.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $payment->status;
        $payment->markAsRefunded($validated['reason'] ?? null);

        $this->activityService->logUserAction('refund', $payment, [
            'old' => ['status' => $oldStatus],
            'new' => ['status' => 'refunded', 'reason' => $validated['reason'] ?? null],
        ]);

        return response()->json([
            'message' => 'Paiement remboursé.',
            'payment' => $payment->fresh()->load('subscription.event'),
        ]);
    }

    /**
     * Get all subscriptions for admin.
     */
    public function adminSubscriptions(Request $request): JsonResponse
    {
        $query = Subscription::with(['event', 'event.user']);

        // Filter by plan
        if ($plan = $request->input('plan')) {
            $query->where('plan_type', $plan);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('payment_status', $status);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $subscriptions = $query->paginate($request->input('per_page', 15));

        return response()->json($subscriptions);
    }

    /**
     * Cancel a subscription (admin only).
     */
    public function adminSubscriptionCancel(Request $request, Subscription $subscription): JsonResponse
    {
        if (!$subscription->canBeCancelled()) {
            return response()->json([
                'message' => 'Cet abonnement ne peut pas être annulé.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $subscription->payment_status;
        $subscription->cancel($validated['reason'] ?? null);

        $this->activityService->logSubscriptionAction('cancel', $subscription, [
            'old' => ['payment_status' => $oldStatus],
            'new' => ['payment_status' => 'cancelled', 'reason' => $validated['reason'] ?? null],
        ]);

        return response()->json([
            'message' => 'Abonnement annulé.',
            'subscription' => $subscription->fresh()->load('event'),
        ]);
    }

    
    /**
     * Extend a subscription (admin only).
     */
    public function adminSubscriptionExtend(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $oldExpiresAt = $subscription->expires_at;
        $newExpiresAt = $subscription->expires_at
            ? $subscription->expires_at->addDays($validated['days'])
            : now()->addDays($validated['days']);

        $subscription->update(['expires_at' => $newExpiresAt]);

        $this->activityService->logSubscriptionAction('extend', $subscription, [
            'old' => ['expires_at' => $oldExpiresAt?->toISOString()],
            'new' => ['expires_at' => $newExpiresAt->toISOString(), 'days_added' => $validated['days']],
        ]);

        return response()->json([
            'message' => "Abonnement prolongé de {$validated['days']} jours.",
            'subscription' => $subscription->fresh()->load('event.user'),
        ]);
    }

    /**
     * Change subscription plan (admin only).
     */
    public function adminSubscriptionChangePlan(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'plan_type' => 'required|in:starter,pro',
        ]);

        if ($subscription->plan_type === $validated['plan_type']) {
            return response()->json([
                'message' => 'Le plan est déjà ' . $validated['plan_type'] . '.',
            ], 422);
        }

        $oldPlan = $subscription->plan_type;
        $subscription->update(['plan_type' => $validated['plan_type']]);

        $this->activityService->logSubscriptionAction('change_plan', $subscription, [
            'old' => ['plan_type' => $oldPlan],
            'new' => ['plan_type' => $validated['plan_type']],
        ]);

        return response()->json([
            'message' => "Plan changé en {$validated['plan_type']}.",
            'subscription' => $subscription->fresh()->load('event.user'),
        ]);
    }

/*
    |--------------------------------------------------------------------------
    | Admin Activity Logs Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get admin activity logs.
     */
    public function adminActivityLogs(Request $request): JsonResponse
    {
        $filters = [
            'admin_id' => $request->input('admin_id'),
            'action' => $request->input('action'),
            'model_type' => $request->input('model_type'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_dir' => $request->input('sort_dir', 'desc'),
        ];

        $perPage = $request->input('per_page', 15);
        $logs = $this->activityService->getActivityLogs($filters, $perPage);

        return response()->json($logs);
    }

    /**
     * Get admin activity statistics.
     */
    public function adminActivityStats(): JsonResponse
    {
        $stats = $this->activityService->getActivityStats();

        return response()->json([
            'stats' => $stats,
        ]);
    }
}
