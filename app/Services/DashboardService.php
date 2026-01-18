<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get admin dashboard statistics.
     */
    public function getAdminStats(): array
    {
        return [
            'users' => $this->getUserStats(),
            'events' => $this->getEventStats(),
            'revenue' => $this->getRevenueStats(),
            'subscriptions' => $this->getSubscriptionStats(),
        ];
    }

    /**
     * Get admin dashboard stats with filters and trends (like user dashboard).
     */
    public function getAdminDashboardStatsWithFilters(string $period, ?array $customRange = null): array
    {
        // Calculate current and previous period dates
        $currentPeriod = $this->calculatePeriodDates($period, $customRange);
        $previousPeriod = $this->calculatePreviousPeriodDates($currentPeriod);

        // Users stats
        $currentUsersQuery = User::query();
        $previousUsersQuery = User::query();
        
        if ($currentPeriod !== null) {
            $currentUsersQuery->whereBetween('created_at', [$currentPeriod['start'], $currentPeriod['end']]);
        }
        $currentUsers = $currentUsersQuery->get();
        
        if ($previousPeriod !== null) {
            $previousUsersQuery->whereBetween('created_at', [$previousPeriod['start'], $previousPeriod['end']]);
        }
        $previousUsers = $previousUsersQuery->get();
        
        $usersActiveQuery = User::whereHas('events', function($q) use ($currentPeriod) {
            if ($currentPeriod !== null) {
                $q->whereBetween('created_at', [$currentPeriod['start'], $currentPeriod['end']]);
            }
        });
        $usersActive = $usersActiveQuery->count();
        $usersInactive = $currentUsers->count() - $usersActive;
        $usersNew = $currentUsers->where('created_at', '>=', now()->startOfMonth())->count();

        $usersTrend = $this->calculateTrend($currentUsers->count(), $previousUsers->count());
        $usersBreakdown = [
            ['label' => 'Actifs', 'value' => $usersActive, 'color' => '#10B981'],
            ['label' => 'Inactifs', 'value' => $usersInactive, 'color' => '#6b7280'],
            ['label' => 'Nouveaux', 'value' => $usersNew, 'color' => '#4F46E5'],
        ];

        // Events stats
        $currentEventsQuery = Event::query();
        $previousEventsQuery = Event::query();
        
        if ($currentPeriod !== null) {
            $currentEventsQuery->whereBetween('created_at', [$currentPeriod['start'], $currentPeriod['end']]);
        }
        $currentEvents = $currentEventsQuery->get();
        
        if ($previousPeriod !== null) {
            $previousEventsQuery->whereBetween('created_at', [$previousPeriod['start'], $previousPeriod['end']]);
        }
        $previousEvents = $previousEventsQuery->get();
        
        $eventsActive = $currentEvents->whereIn('status', ['upcoming', 'ongoing'])->count();
        $eventsCompleted = $currentEvents->where('status', 'completed')->count();
        $eventsOngoing = $currentEvents->where('status', 'ongoing')->count();

        $eventsTrend = $this->calculateTrend($currentEvents->count(), $previousEvents->count());
        $eventsBreakdown = [
            ['label' => 'Actifs', 'value' => $eventsActive, 'color' => '#10B981'],
            ['label' => 'Terminés', 'value' => $eventsCompleted, 'color' => '#6b7280'],
            ['label' => 'En cours', 'value' => $eventsOngoing, 'color' => '#F59E0B'],
        ];

        // Subscriptions stats
        $currentSubscriptionsQuery = Subscription::query();
        $previousSubscriptionsQuery = Subscription::query();
        
        if ($currentPeriod !== null) {
            $currentSubscriptionsQuery->whereBetween('created_at', [$currentPeriod['start'], $currentPeriod['end']]);
        }
        $currentSubscriptions = $currentSubscriptionsQuery->get();
        
        if ($previousPeriod !== null) {
            $previousSubscriptionsQuery->whereBetween('created_at', [$previousPeriod['start'], $previousPeriod['end']]);
        }
        $previousSubscriptions = $previousSubscriptionsQuery->get();
        
        $subscriptionsActive = $currentSubscriptions->filter(fn($s) => $s->isActive())->count();
        $subscriptionsTrial = $currentSubscriptions->where('plan_type', 'essai-gratuit')->count();
        $subscriptionsPro = $currentSubscriptions->where('plan_type', 'pro')->count();
        $subscriptionsAgence = $currentSubscriptions->where('plan_type', 'agence')->count();

        $subscriptionsTrend = $this->calculateTrend($currentSubscriptions->count(), $previousSubscriptions->count());
        $subscriptionsBreakdown = [
            ['label' => 'Essai', 'value' => $subscriptionsTrial, 'color' => '#4F46E5'],
            ['label' => 'Pro', 'value' => $subscriptionsPro, 'color' => '#10B981'],
            ['label' => 'Agence', 'value' => $subscriptionsAgence, 'color' => '#7C3AED'],
        ];

        // Revenue stats
        $currentPaymentsQuery = Payment::where('status', 'completed');
        $previousPaymentsQuery = Payment::where('status', 'completed');
        
        if ($currentPeriod !== null) {
            $currentPaymentsQuery->whereBetween('created_at', [$currentPeriod['start'], $currentPeriod['end']]);
        }
        $currentPayments = $currentPaymentsQuery->get();
        
        if ($previousPeriod !== null) {
            $previousPaymentsQuery->whereBetween('created_at', [$previousPeriod['start'], $previousPeriod['end']]);
        }
        $previousPayments = $previousPaymentsQuery->get();
        
        $revenueTotal = $currentPayments->sum('amount');
        $revenuePaid = $currentPayments->sum('amount');
        
        $revenuePendingQuery = Payment::where('status', 'pending');
        if ($currentPeriod !== null) {
            $revenuePendingQuery->whereBetween('created_at', [$currentPeriod['start'], $currentPeriod['end']]);
        }
        $revenuePending = $revenuePendingQuery->sum('amount');
        
        $revenueRefundedQuery = Payment::where('status', 'refunded');
        if ($currentPeriod !== null) {
            $revenueRefundedQuery->whereBetween('created_at', [$currentPeriod['start'], $currentPeriod['end']]);
        }
        $revenueRefunded = $revenueRefundedQuery->sum('amount');

        $previousRevenue = $previousPayments->sum('amount');
        $revenueTrend = $this->calculateTrend($revenueTotal, $previousRevenue);
        $revenueBreakdown = [
            ['label' => 'Payé', 'value' => $revenuePaid, 'color' => '#10B981'],
            ['label' => 'En attente', 'value' => $revenuePending, 'color' => '#F59E0B'],
            ['label' => 'Remboursé', 'value' => $revenueRefunded, 'color' => '#EF4444'],
        ];

        return [
            'users' => [
                'total' => $currentUsers->count(),
                'breakdown' => $usersBreakdown,
                'trend' => $usersTrend,
            ],
            'events' => [
                'total' => $currentEvents->count(),
                'breakdown' => $eventsBreakdown,
                'trend' => $eventsTrend,
            ],
            'subscriptions' => [
                'total' => $currentSubscriptions->count(),
                'breakdown' => $subscriptionsBreakdown,
                'trend' => $subscriptionsTrend,
            ],
            'revenue' => [
                'total' => $revenueTotal,
                'breakdown' => $revenueBreakdown,
                'trend' => $revenueTrend,
            ],
        ];
    }

    /**
     * Get plan distribution for admin dashboard.
     */
    public function getPlanDistribution(): array
    {
        $subscriptions = Subscription::where(function($q) {
            $q->where('payment_status', 'paid')
              ->orWhere(function($subQ) {
                  $subQ->where('plan_type', 'essai-gratuit')
                       ->whereNotNull('expires_at')
                       ->where('expires_at', '>', now());
              });
        })->get();

        $grouped = $subscriptions->groupBy('plan_type');

        $planColors = [
            'essai-gratuit' => '#4F46E5',
            'pro' => '#10B981',
            'agence' => '#7C3AED',
        ];

        $planLabels = [
            'essai-gratuit' => 'Essai Gratuit',
            'pro' => 'Pro',
            'agence' => 'Agence',
        ];

        return $grouped->map(function ($group, $planType) use ($planColors, $planLabels) {
            return [
                'name' => $planLabels[$planType] ?? ucfirst($planType),
                'value' => $group->count(),
                'color' => $planColors[$planType] ?? '#6B7280',
            ];
        })->values()->toArray();
    }

    /**
     * Get user dashboard statistics.
     */
    public function getUserStats(?User $user = null): array
    {
        if ($user) {
            return $this->getUserPersonalStats($user);
        }

        return [
            'total' => User::count(),
            'active' => User::whereHas('events')->count(),
            'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'admin' => User::where('role', 'admin')->count(),
        ];
    }

    /**
     * Get user's personal stats.
     */
    public function getUserPersonalStats(User $user): array
    {
        $events = $user->events;
        $collaborations = $user->collaboratingEvents;

        // Calculate total budget from all events (estimated_budget field + budgetItems sum)
        $totalBudget = $events->sum(function ($event) {
            // Use the event's estimated_budget if set, otherwise sum budget items
            if ($event->estimated_budget && $event->estimated_budget > 0) {
                return $event->estimated_budget;
            }
            return $event->budgetItems()->sum('estimated_cost');
        });

        // Calculate confirmed guests across all events
        $confirmedGuests = $events->sum(fn($e) => $e->guests()->where('rsvp_status', 'accepted')->count());

        return [
            'events_count' => $events->count(),
            'active_events' => $events->whereIn('status', ['upcoming', 'ongoing'])->count(),
            'completed_events' => $events->where('status', 'completed')->count(),
            'collaborations_count' => $collaborations->count(),
            'total_guests' => $events->sum(fn($e) => $e->guests()->count()),
            'guests_confirmed' => $confirmedGuests,
            'total_tasks' => $events->sum(fn($e) => $e->tasks()->count()),
            'completed_tasks' => $events->sum(fn($e) => $e->tasks()->where('status', 'completed')->count()),
            'pending_tasks' => $events->sum(fn($e) => $e->tasks()->whereIn('status', ['todo', 'in_progress'])->count()),
            'upcoming_events' => $events->where('date', '>=', now())->where('date', '<=', now()->addMonth())->count(),
            'total_budget' => $totalBudget,
        ];
    }

    /**
     * Get event statistics.
     */
    public function getEventStats(): array
    {
        $events = Event::all();

        return [
            'total' => $events->count(),
            'active' => $events->whereIn('status', ['upcoming', 'ongoing'])->count(),
            'completed' => $events->where('status', 'completed')->count(),
            'cancelled' => $events->where('status', 'cancelled')->count(),
            'by_type' => $events->groupBy('type')->map->count()->toArray(),
            'count' => Payment::count(),
            'pending_count' => Payment::where('status', 'pending')->count(),
            'completed_count' => Payment::where('status', 'completed')->count(),
            'by_status' => $events->groupBy('status')->map->count()->toArray(),
            'count' => Payment::count(),
            'pending_count' => Payment::where('status', 'pending')->count(),
            'completed_count' => Payment::where('status', 'completed')->count(),
            'new_this_month' => Event::where('created_at', '>=', now()->startOfMonth())->count(),
            'upcoming' => Event::where('date', '>=', now())->where('date', '<=', now()->addMonth())->count(),
        ];
    }

    /**
     * Get revenue statistics.
     */
    public function getRevenueStats(): array
    {
        $completedPayments = Payment::where('status', 'completed');

        return [
            'total' => $completedPayments->sum('amount'),
            'this_month' => $completedPayments->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'last_month' => $completedPayments
                ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                ->sum('amount'),
            'this_year' => $completedPayments->where('created_at', '>=', now()->startOfYear())->sum('amount'),
            'by_method' => Payment::where('status', 'completed')
                ->selectRaw('payment_method, SUM(amount) as total')
                ->groupBy('payment_method')
                ->pluck('total', 'payment_method')
                ->toArray(),
            'count' => Payment::count(),
            'pending_count' => Payment::where('status', 'pending')->count(),
            'completed_count' => Payment::where('status', 'completed')->count(),
        ];
    }

    /**
     * Get subscription statistics.
     */
    public function getSubscriptionStats(): array
    {
        $subscriptions = Subscription::all();

        return [
            'total' => $subscriptions->count(),
            'active' => $subscriptions->filter(fn($s) => $s->isActive())->count(),
            'pending' => $subscriptions->where('payment_status', 'pending')->count(),
            'by_plan' => $subscriptions->groupBy('plan_type')->map->count()->toArray(),
            'count' => Payment::count(),
            'pending_count' => Payment::where('status', 'pending')->count(),
            'completed_count' => Payment::where('status', 'completed')->count(),
            'conversion_rate' => $this->calculateConversionRate(),
        ];
    }

    /**
     * Calculate conversion rate.
     */
    protected function calculateConversionRate(): float
    {
        $totalEvents = Event::count();
        $paidSubscriptions = Subscription::where('payment_status', 'paid')->count();

        if ($totalEvents === 0) {
            return 0;
        }

        return round(($paidSubscriptions / $totalEvents) * 100, 2);
    }

    /**
     * Get event-specific dashboard data.
     */
    public function getEventDashboard(Event $event): array
    {
        $event->load(['guests', 'tasks', 'budgetItems', 'photos', 'collaborators', 'subscription']);

        return [
            'event' => $event,
            'guests' => $this->getEventGuestStats($event),
            'tasks' => $this->getEventTaskStats($event),
            'budget' => $this->getEventBudgetStats($event),
            'timeline' => $this->getEventTimeline($event),
            'upcoming_tasks' => $this->getUpcomingTasks($event),
            'recent_activity' => $this->getRecentActivity($event),
        ];
    }

    /**
     * Get guest statistics for an event.
     */
    public function getEventGuestStats(Event $event): array
    {
        $guests = $event->guests;

        return [
            'total' => $guests->count(),
            'accepted' => $guests->where('rsvp_status', 'accepted')->count(),
            'declined' => $guests->where('rsvp_status', 'declined')->count(),
            'pending' => $guests->where('rsvp_status', 'pending')->count(),
            'maybe' => $guests->where('rsvp_status', 'maybe')->count(),
            'checked_in' => $guests->where('checked_in', true)->count(),
            'invitation_sent' => $guests->whereNotNull('invitation_sent_at')->count(),
            'response_rate' => $this->calculateResponseRate($guests),
        ];
    }

    /**
     * Calculate response rate.
     */
    protected function calculateResponseRate(Collection $guests): float
    {
        $total = $guests->count();
        $responded = $guests->whereIn('rsvp_status', ['accepted', 'declined', 'maybe'])->count();

        if ($total === 0) {
            return 0;
        }

        return round(($responded / $total) * 100, 2);
    }

    /**
     * Get task statistics for an event.
     */
    public function getEventTaskStats(Event $event): array
    {
        $tasks = $event->tasks;

        return [
            'total' => $tasks->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'todo' => $tasks->where('status', 'todo')->count(),
            'cancelled' => $tasks->where('status', 'cancelled')->count(),
            'overdue' => $tasks->where('status', '!=', 'completed')
                ->where('due_date', '<', now())
                ->count(),
            'by_priority' => $tasks->groupBy('priority')->map->count()->toArray(),
            'count' => Payment::count(),
            'pending_count' => Payment::where('status', 'pending')->count(),
            'completed_count' => Payment::where('status', 'completed')->count(),
            'completion_rate' => $this->calculateCompletionRate($tasks),
        ];
    }

    /**
     * Calculate task completion rate.
     */
    protected function calculateCompletionRate(Collection $tasks): float
    {
        $total = $tasks->where('status', '!=', 'cancelled')->count();
        $completed = $tasks->where('status', 'completed')->count();

        if ($total === 0) {
            return 0;
        }

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Get budget statistics for an event.
     */
    public function getEventBudgetStats(Event $event): array
    {
        $items = $event->budgetItems;

        $totalEstimated = $items->sum('estimated_cost');
        $totalActual = $items->sum('actual_cost');
        $totalPaid = $items->where('paid', true)->sum('actual_cost');

        return [
            'total_estimated' => $totalEstimated,
            'total_actual' => $totalActual,
            'total_paid' => $totalPaid,
            'total_unpaid' => $totalActual - $totalPaid,
            'variance' => $totalActual - $totalEstimated,
            'variance_percent' => $totalEstimated > 0
                ? round((($totalActual - $totalEstimated) / $totalEstimated) * 100, 2)
                : 0,
            'event_budget' => $event->estimated_budget ?? 0,
            'budget_used_percent' => $event->estimated_budget > 0
                ? round(($totalActual / $event->estimated_budget) * 100, 2)
                : 0,
            'items_count' => $items->count(),
            'paid_items_count' => $items->where('paid', true)->count(),
            'by_category' => $items->groupBy('category')
                ->map(fn($group) => [
                    'estimated' => $group->sum('estimated_cost'),
                    'actual' => $group->sum('actual_cost'),
                    'count' => $group->count(),
                ])
                ->toArray(),
            'count' => Payment::count(),
            'pending_count' => Payment::where('status', 'pending')->count(),
            'completed_count' => Payment::where('status', 'completed')->count(),
        ];
    }

    /**
     * Get event timeline.
     */
    public function getEventTimeline(Event $event): array
    {
        $timeline = [];

        // Event creation
        $timeline[] = [
            'date' => $event->created_at,
            'type' => 'event_created',
            'title' => 'Événement créé',
            'description' => "L'événement \"{$event->title}\" a été créé.",
        ];

        // Tasks with due dates
        foreach ($event->tasks()->whereNotNull('due_date')->get() as $task) {
            $timeline[] = [
                'date' => $task->due_date,
                'type' => 'task_due',
                'title' => $task->title,
                'description' => "Échéance de la tâche",
                'status' => $task->status,
            ];
        }

        // Event date
        if ($event->date) {
            $timeline[] = [
                'date' => $event->date,
                'type' => 'event_date',
                'title' => 'Jour J',
                'description' => "Date de l'événement",
            ];
        }

        // Sort by date
        usort($timeline, fn($a, $b) => $a['date'] <=> $b['date']);

        return $timeline;
    }

    /**
     * Get upcoming tasks for an event.
     */
    public function getUpcomingTasks(Event $event, int $limit = 5): Collection
    {
        return $event->tasks()
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activity for an event.
     */
    public function getRecentActivity(Event $event, int $limit = 10): array
    {
        $activity = [];

        // Recent guests
        foreach ($event->guests()->latest()->limit(5)->get() as $guest) {
            $activity[] = [
                'date' => $guest->created_at,
                'type' => 'guest_added',
                'title' => "Invité ajouté : {$guest->name}",
            ];
        }

        // Recent tasks
        foreach ($event->tasks()->latest()->limit(5)->get() as $task) {
            $activity[] = [
                'date' => $task->created_at,
                'type' => 'task_created',
                'title' => "Tâche créée : {$task->title}",
            ];
        }

        // Recent budget items
        foreach ($event->budgetItems()->latest()->limit(5)->get() as $item) {
            $activity[] = [
                'date' => $item->created_at,
                'type' => 'budget_item_added',
                'title' => "Budget : {$item->name} ajouté",
            ];
        }

        // Sort and limit
        usort($activity, fn($a, $b) => $b['date'] <=> $a['date']);

        return array_slice($activity, 0, $limit);
    }

    /**
     * Get chart data for admin dashboard.
     */
    public function getChartData(string $period = 'month'): array
    {
        $startDate = match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        return [
            'revenue' => $this->getRevenueChartData($startDate),
            'events' => $this->getEventsChartData($startDate),
            'users' => $this->getUsersChartData($startDate),
        ];
    }

    /**
     * Get revenue chart data.
     */
    protected function getRevenueChartData(Carbon $startDate): array
    {
        return Payment::where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') as date, SUM(amount) as total")
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD')"))
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'total' => (float) $row->total,
            ])
            ->toArray();
    }

    /**
     * Get events chart data.
     */
    protected function getEventsChartData(Carbon $startDate): array
    {
        return Event::where('created_at', '>=', $startDate)
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') as date, COUNT(*) as count")
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD')"))
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Get users chart data.
     */
    protected function getUsersChartData(Carbon $startDate): array
    {
        return User::where('created_at', '>=', $startDate)
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM-DD') as date, COUNT(*) as count")
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD')"))
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Get user events query (owned + collaborated).
     */
    protected function getUserEventsQuery(User $user)
    {
        $ownedEventIds = $user->events()->pluck('id');
        $collaboratingEventIds = $user->collaborations()
            ->whereNotNull('accepted_at')
            ->pluck('event_id');

        $eventIds = $ownedEventIds->merge($collaboratingEventIds)->unique();

        return Event::whereIn('id', $eventIds);
    }

    /**
     * Calculate period dates based on period string or custom range.
     */
    public function calculatePeriodDates(string $period, ?array $customRange = null): ?array
    {
        // Return null for "all" to indicate no date filter
        if ($period === 'all') {
            return null;
        }

        if ($period === 'custom' && $customRange) {
            $start = $customRange['start'] instanceof Carbon 
                ? $customRange['start']->copy()->startOfDay()
                : Carbon::parse($customRange['start'])->startOfDay();
            $end = $customRange['end'] instanceof Carbon 
                ? $customRange['end']->copy()->endOfDay()
                : Carbon::parse($customRange['end'])->endOfDay();
            
            return [
                'start' => $start,
                'end' => $end,
            ];
        }

        $end = Carbon::now()->endOfDay();

        $start = match ($period) {
            '7days' => Carbon::now()->subDays(7)->startOfDay(),
            '1month' => Carbon::now()->subMonth()->startOfDay(),
            '3months' => Carbon::now()->subMonths(3)->startOfDay(),
            default => Carbon::now()->subDays(7)->startOfDay(),
        };

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Calculate previous period dates (same duration as current period).
     */
    public function calculatePreviousPeriodDates(?array $currentPeriod): ?array
    {
        // If no current period (all data), return null for previous period too
        if ($currentPeriod === null) {
            return null;
        }

        $duration = $currentPeriod['end']->diffInDays($currentPeriod['start']);

        return [
            'start' => $currentPeriod['start']->copy()->subDays($duration + 1)->startOfDay(),
            'end' => $currentPeriod['start']->copy()->subDay()->endOfDay(),
        ];
    }

    /**
     * Calculate trend (percentage change).
     */
    public function calculateTrend(float $current, float $previous): array
    {
        if ($previous == 0) {
            return [
                'value' => $current > 0 ? 100.0 : 0.0,
                'isPositive' => $current > 0,
                'previousValue' => 0.0,
            ];
        }

        $percentage = (($current - $previous) / $previous) * 100;

        return [
            'value' => abs(round($percentage, 1)),
            'isPositive' => $percentage >= 0,
            'previousValue' => $previous,
        ];
    }

    /**
     * Get user stats with filters (period + event type) and trends.
     */
    public function getUserStatsWithFilters(User $user, string $period, ?string $eventType = 'all', ?array $customRange = null): array
    {
        // Calculate current and previous period dates
        $currentPeriod = $this->calculatePeriodDates($period, $customRange);
        $previousPeriod = $this->calculatePreviousPeriodDates($currentPeriod);

        // Get events query
        $eventsQuery = $this->getUserEventsQuery($user);

        // Filter by event type if not 'all'
        if ($eventType !== 'all') {
            $eventsQuery->where('type', $eventType);
        }

        // Get events in current period with relations
        $currentEventsQuery = (clone $eventsQuery)->with(['guests', 'tasks', 'budgetItems']);
        if ($currentPeriod !== null) {
            $currentEventsQuery->whereBetween('created_at', [$currentPeriod['start'], $currentPeriod['end']]);
        }
        $currentEvents = $currentEventsQuery->get();

        // Get events in previous period with relations
        $previousEventsQuery = (clone $eventsQuery)->with(['guests', 'tasks', 'budgetItems']);
        if ($previousPeriod !== null) {
            $previousEventsQuery->whereBetween('created_at', [$previousPeriod['start'], $previousPeriod['end']]);
        }
        $previousEvents = $previousEventsQuery->get();

        // Calculate current stats
        $currentStats = $this->calculateStatsForEvents($currentEvents);

        // Calculate previous stats
        $previousStats = $this->calculateStatsForEvents($previousEvents);

        // Calculate trends
        $eventsTrend = $this->calculateTrend($currentStats['events']['total'], $previousStats['events']['total']);
        $guestsTrend = $this->calculateTrend($currentStats['guests']['total'], $previousStats['guests']['total']);
        $tasksTrend = $this->calculateTrend($currentStats['tasks']['total'], $previousStats['tasks']['total']);
        $budgetTrend = $this->calculateTrend($currentStats['budget']['total'], $previousStats['budget']['total']);

        return [
            'events' => [
                'total' => $currentStats['events']['total'],
                'breakdown' => $currentStats['events']['breakdown'],
                'trend' => $eventsTrend,
            ],
            'guests' => [
                'total' => $currentStats['guests']['total'],
                'breakdown' => $currentStats['guests']['breakdown'],
                'trend' => $guestsTrend,
            ],
            'tasks' => [
                'total' => $currentStats['tasks']['total'],
                'breakdown' => $currentStats['tasks']['breakdown'],
                'trend' => $tasksTrend,
            ],
            'budget' => [
                'total' => $currentStats['budget']['total'],
                'breakdown' => $currentStats['budget']['breakdown'],
                'trend' => $budgetTrend,
            ],
        ];
    }

    /**
     * Calculate stats for a collection of events.
     */
    protected function calculateStatsForEvents(Collection $events): array
    {
        $now = Carbon::now();

        // Events breakdown
        $eventsUpcoming = $events->filter(fn($e) => $e->date && $e->date->isFuture())->count();
        $eventsInProgress = $events->filter(fn($e) => $e->date && $e->date->isPast() && $e->status === 'confirmed')->count();
        $eventsCompleted = $events->filter(fn($e) => $e->status === 'completed')->count();

        // Guests breakdown
        $guestsAccepted = 0;
        $guestsDeclined = 0;
        $guestsPending = 0;
        $totalGuests = 0;

        foreach ($events as $event) {
            $guests = $event->guests;
            $totalGuests += $guests->count();
            $guestsAccepted += $guests->where('rsvp_status', 'accepted')->count();
            $guestsDeclined += $guests->where('rsvp_status', 'declined')->count();
            $guestsPending += $guests->where('rsvp_status', 'pending')->count();
        }

        // Tasks breakdown
        $tasksTodo = 0;
        $tasksInProgress = 0;
        $tasksCompleted = 0;
        $totalTasks = 0;

        foreach ($events as $event) {
            $tasks = $event->tasks;
            $totalTasks += $tasks->count();
            $tasksTodo += $tasks->where('status', 'todo')->count();
            $tasksInProgress += $tasks->where('status', 'in_progress')->count();
            $tasksCompleted += $tasks->where('status', 'completed')->count();
        }

        // Budget breakdown
        $totalBudget = 0;
        $spentBudget = 0;

        foreach ($events as $event) {
            if ($event->estimated_budget && $event->estimated_budget > 0) {
                $totalBudget += $event->estimated_budget;
            } else {
                $totalBudget += $event->budgetItems()->sum('estimated_cost');
            }
            $spentBudget += $event->budgetItems()->sum('actual_cost');
        }

        $remainingBudget = $totalBudget - $spentBudget;

        return [
            'events' => [
                'total' => $events->count(),
                'breakdown' => [
                    ['label' => 'À venir', 'value' => $eventsUpcoming, 'color' => '#4F46E5'],
                    ['label' => 'En cours', 'value' => $eventsInProgress, 'color' => '#F97316'],
                    ['label' => 'Terminés', 'value' => $eventsCompleted, 'color' => '#10B981'],
                ],
            ],
            'guests' => [
                'total' => $totalGuests,
                'breakdown' => [
                    ['label' => 'Acceptés', 'value' => $guestsAccepted, 'color' => '#10B981'],
                    ['label' => 'Déclinés', 'value' => $guestsDeclined, 'color' => '#EF4444'],
                    ['label' => 'En attente', 'value' => $guestsPending, 'color' => '#F97316'],
                ],
            ],
            'tasks' => [
                'total' => $totalTasks,
                'breakdown' => [
                    ['label' => 'À faire', 'value' => $tasksTodo, 'color' => '#EF4444'],
                    ['label' => 'En cours', 'value' => $tasksInProgress, 'color' => '#F97316'],
                    ['label' => 'Terminées', 'value' => $tasksCompleted, 'color' => '#10B981'],
                ],
            ],
            'budget' => [
                'total' => $totalBudget,
                'breakdown' => [
                    ['label' => 'Dépensé', 'value' => $spentBudget, 'color' => '#EF4444'],
                    ['label' => 'Restant', 'value' => max(0, $remainingBudget), 'color' => '#10B981'],
                ],
            ],
        ];
    }

    /**
     * Get confirmations data with filters, pagination, search and sort.
     */
    public function getConfirmationsData(User $user, string $period, ?string $eventType, array $filters): array
    {
        // Calculate period dates
        $periodDates = $this->calculatePeriodDates($period);

        // Get events query
        $eventsQuery = $this->getUserEventsQuery($user);
        
        // Apply date filter only if period is not "all"
        if ($periodDates !== null) {
            $eventsQuery->whereBetween('created_at', [$periodDates['start'], $periodDates['end']]);
        }

        // Filter by event type if not 'all'
        if ($eventType !== 'all') {
            $eventsQuery->where('type', $eventType);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $eventsQuery->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('type', 'ilike', "%{$search}%");
            });
        }

        // Get all events for calculations with relations
        $allEvents = $eventsQuery->with('guests')->get();

        // Calculate confirmations for each event
        $eventsData = $allEvents->map(function ($event) {
            $guests = $event->guests;
            $confirmed = $guests->where('rsvp_status', 'accepted')->count();
            $declined = $guests->where('rsvp_status', 'declined')->count();
            $pending = $guests->where('rsvp_status', 'pending')->count();
            $total = $guests->count();
            $confirmRate = $total > 0 ? round(($confirmed / $total) * 100) : 0;

            return [
                'id' => $event->id,
                'name' => $event->title,
                'type' => $event->type,
                'month' => $event->date ? $event->date->format('M') : null,
                'monthIndex' => $event->date ? (int) $event->date->format('n') : 0,
                'confirmed' => $confirmed,
                'declined' => $declined,
                'pending' => $pending,
                'total' => $total,
                'confirmRate' => $confirmRate,
            ];
        });

        // Sort
        $sortBy = $filters['sort_by'] ?? 'confirmRate';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $eventsData = $eventsData->sortBy(function ($event) use ($sortBy, $sortOrder) {
            $value = $event[$sortBy];
            return $sortOrder === 'asc' ? $value : -$value;
        })->values();

        // Pagination
        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 5;
        $total = $eventsData->count();
        $offset = ($page - 1) * $perPage;
        $paginatedEvents = $eventsData->slice($offset, $perPage)->values();

        // Calculate totals
        $totalConfirmed = $eventsData->sum('confirmed');
        $totalDeclined = $eventsData->sum('declined');
        $totalPending = $eventsData->sum('pending');
        $totalInvites = $totalConfirmed + $totalDeclined + $totalPending;

        return [
            'events' => $paginatedEvents->toArray(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
            'summary' => [
                'total_events' => $total,
                'total_guests' => $totalInvites,
            ],
        ];
    }

    /**
     * Get events by type data.
     */
    public function getEventsByTypeData(User $user, string $period, ?string $eventType): array
    {
        // Calculate period dates
        $periodDates = $this->calculatePeriodDates($period);

        // Get events query
        $eventsQuery = $this->getUserEventsQuery($user);
        
        // Apply date filter only if period is not "all"
        if ($periodDates !== null) {
            $eventsQuery->whereBetween('created_at', [$periodDates['start'], $periodDates['end']]);
        }

        // Filter by event type if not 'all'
        if ($eventType !== 'all') {
            $eventsQuery->where('type', $eventType);
        }

        // Get events and group by type
        $events = $eventsQuery->get();
        $groupedByType = $events->groupBy('type');

        // Type colors mapping
        $typeColors = [
            'mariage' => '#E91E8C',
            'anniversaire' => '#4F46E5',
            'conférence' => '#F59E0B',
            'fête privée' => '#10B981',
            'séminaire' => '#8B5CF6',
            'baptême' => '#06B6D4',
            'gala' => '#EC4899',
            'baby_shower' => '#10B981',
            'soiree' => '#F59E0B',
            'brunch' => '#8B5CF6',
            'autre' => '#6B7280',
        ];

        // Type labels mapping
        $typeLabels = [
            'mariage' => 'Mariage',
            'anniversaire' => 'Anniversaire',
            'conférence' => 'Conférence',
            'fête privée' => 'Fête privée',
            'séminaire' => 'Séminaire',
            'baptême' => 'Baptême',
            'gala' => 'Gala',
            'baby_shower' => 'Baby Shower',
            'soiree' => 'Soirée',
            'brunch' => 'Brunch',
            'autre' => 'Autre',
        ];

        $result = $groupedByType->map(function ($typeEvents, $type) use ($typeColors, $typeLabels) {
            return [
                'name' => $typeLabels[$type] ?? ucfirst($type),
                'value' => $typeEvents->count(),
                'color' => $typeColors[$type] ?? '#6B7280',
            ];
        })->values()->toArray();

        // If filtering by specific type, return only that type
        if ($eventType !== 'all' && $eventType) {
            $result = array_filter($result, fn($item) => 
                strtolower($item['name']) === strtolower($eventType) ||
                strtolower($item['name']) === strtolower($typeLabels[$eventType] ?? $eventType)
            );
        }

        return $result;
    }

    /**
     * Get recent activity for user.
     */
    public function getUserRecentActivity(User $user, int $limit = 6): array
    {
        $activities = collect();

        // Get all user events (owned + collaborated) with relations
        $eventsQuery = $this->getUserEventsQuery($user);
        $events = $eventsQuery->with(['guests', 'tasks'])->get();

        // Recent RSVP activities
        foreach ($events as $event) {
            $recentGuests = $event->guests()
                ->whereNotNull('rsvp_status')
                ->where('rsvp_status', '!=', 'pending')
                ->latest('updated_at')
                ->limit(3)
                ->get();

            foreach ($recentGuests as $guest) {
                $activities->push([
                    'id' => 'rsvp_' . $guest->id,
                    'type' => 'rsvp',
                    'message' => $guest->rsvp_status === 'accepted'
                        ? "{$guest->name} a confirmé sa présence"
                        : "{$guest->name} a décliné l'invitation",
                    'event' => $event->title,
                    'timestamp' => $guest->updated_at,
                    'icon_type' => $guest->rsvp_status === 'accepted' ? 'UserPlus' : 'UserX',
                ]);
            }
        }

        // Recent task completions
        foreach ($events as $event) {
            $completedTasks = $event->tasks()
                ->where('status', 'completed')
                ->latest('updated_at')
                ->limit(2)
                ->get();

            foreach ($completedTasks as $task) {
                $activities->push([
                    'id' => 'task_' . $task->id,
                    'type' => 'task',
                    'message' => "Tâche '{$task->title}' complétée",
                    'event' => $event->title,
                    'timestamp' => $task->updated_at,
                    'icon_type' => 'CheckCircle',
                ]);
            }
        }

        // Recent payments (if Payment model has event relationship)
        // This would require checking the Payment model structure

        // Recent event creations
        $recentEvents = $events->sortByDesc('created_at')->take(2);
        foreach ($recentEvents as $event) {
            $activities->push([
                'id' => 'event_' . $event->id,
                'type' => 'event',
                'message' => "Nouvel événement créé",
                'event' => $event->title,
                'timestamp' => $event->created_at,
                'icon_type' => 'Calendar',
            ]);
        }

        // Sort by timestamp and limit
        $activities = $activities->sortByDesc('timestamp')->take($limit)->values();

        return $activities->map(function ($activity) {
            $timestamp = $activity['timestamp'];
            // Ensure it's a Carbon instance
            if (!$timestamp instanceof Carbon) {
                $timestamp = Carbon::parse($timestamp);
            }
            
            return [
                'id' => $activity['id'],
                'type' => $activity['type'],
                'message' => $activity['message'],
                'event' => $activity['event'],
                'time' => $this->formatRelativeTime($timestamp),
                'icon_type' => $activity['icon_type'],
            ];
        })->toArray();
    }

    /**
     * Format timestamp to relative time (e.g., "Il y a 5 min").
     */
    protected function formatRelativeTime(Carbon $timestamp): string
    {
        $diff = $timestamp->diffForHumans(now(), true);

        // Translate to French
        $translations = [
            'seconds' => 'secondes',
            'second' => 'seconde',
            'minutes' => 'minutes',
            'minute' => 'minute',
            'hours' => 'heures',
            'hour' => 'heure',
            'days' => 'jours',
            'day' => 'jour',
            'weeks' => 'semaines',
            'week' => 'semaine',
            'months' => 'mois',
            'month' => 'mois',
            'years' => 'années',
            'year' => 'année',
        ];

        foreach ($translations as $en => $fr) {
            $diff = str_replace($en, $fr, $diff);
        }

        return "Il y a {$diff}";
    }
}
