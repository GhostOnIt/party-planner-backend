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
            'active_events' => $events->whereIn('status', ['planning', 'confirmed'])->count(),
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
            'active' => $events->whereIn('status', ['planning', 'confirmed'])->count(),
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
}
