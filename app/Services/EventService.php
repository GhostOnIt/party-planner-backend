<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Events\EventCreated;
use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(
        protected EntitlementService $entitlementService
    ) {}

    /**
     * Default budget estimates by event type (in XAF).
     */
    protected array $budgetEstimates = [
        'mariage' => 2000000,
        'anniversaire' => 300000,
        'baby_shower' => 200000,
        'soiree' => 500000,
        'brunch' => 150000,
        'autre' => 250000,
    ];

    /**
     * Create a new event with optional template application.
     */
    public function create(User $user, array $data, ?int $templateId = null): Event
    {
        return DB::transaction(function () use ($user, $data, $templateId) {
            // Set default estimated budget if not provided
            if (empty($data['estimated_budget'])) {
                $data['estimated_budget'] = $this->getEstimatedBudget($data['type']);
            }

            // Set default status
            if (empty($data['status'])) {
                $data['status'] = config('partyplanner.events.default_status', 'upcoming');
            }

            // Set max_guests_allowed, max_collaborators_allowed, and max_photos_allowed based on current subscription
            if (!isset($data['max_guests_allowed'])) {
                $data['max_guests_allowed'] = $this->entitlementService->limit($user, 'guests.max_per_event');
            }
            if (!isset($data['max_collaborators_allowed'])) {
                $data['max_collaborators_allowed'] = $this->entitlementService->limit($user, 'collaborators.max_per_event');
            }
            if (!isset($data['max_photos_allowed'])) {
                $data['max_photos_allowed'] = $this->entitlementService->limit($user, 'photos.max_per_event');
            }
            
            // Store enabled features at creation time (so they persist after subscription expires)
            if (!isset($data['features_enabled'])) {
                $entitlements = $this->entitlementService->getEffectiveEntitlements($user);
                // Store only the features that are enabled (true)
                $data['features_enabled'] = array_filter($entitlements['features'] ?? [], fn($value) => $value === true);
            }

            // Create the event
            $event = $user->events()->create($data);

            // Apply template if provided
            if ($templateId) {
                $this->applyTemplate($event, $templateId);
            } else {
                // Auto-apply template based on event type
                $this->autoApplyTemplate($event);
            }

            // Dispatch event created event
            EventCreated::dispatch($event);

            return $event->fresh();
        });
    }

    /**
     * Update an event.
     */
    public function update(Event $event, array $data): Event
    {
        // Recalculate budget if type changed and no custom budget set
        if (isset($data['type']) && $data['type'] !== $event->type) {
            if (empty($data['estimated_budget']) && $event->estimated_budget === $this->getEstimatedBudget($event->type)) {
                $data['estimated_budget'] = $this->getEstimatedBudget($data['type']);
            }
        }

        $event->update($data);

        return $event->fresh();
    }

    /**
     * Duplicate an event.
     */
    public function duplicate(Event $event, User $user, array $overrides = []): Event
    {
        return DB::transaction(function () use ($event, $user, $overrides) {
            // Prepare base data
            $data = [
                'title' => $overrides['title'] ?? $event->title . ' (copie)',
                'type' => $overrides['type'] ?? $event->type,
                'description' => $overrides['description'] ?? $event->description,
                'date' => $overrides['date'] ?? null,
                'time' => $overrides['time'] ?? $event->time,
                'location' => $overrides['location'] ?? $event->location,
                'estimated_budget' => $overrides['estimated_budget'] ?? $event->estimated_budget,
                'theme' => $overrides['theme'] ?? $event->theme,
                'expected_guests_count' => $overrides['expected_guests_count'] ?? $event->expected_guests_count,
                'status' => EventStatus::UPCOMING->value,
            ];

            // Copy limits from original event, or set based on current subscription
            if (isset($overrides['max_guests_allowed'])) {
                $data['max_guests_allowed'] = $overrides['max_guests_allowed'];
            } elseif ($event->max_guests_allowed !== null) {
                // Copy from original event
                $data['max_guests_allowed'] = $event->max_guests_allowed;
            } else {
                // Set based on current subscription
                $data['max_guests_allowed'] = $this->entitlementService->limit($user, 'guests.max_per_event');
            }

            if (isset($overrides['max_collaborators_allowed'])) {
                $data['max_collaborators_allowed'] = $overrides['max_collaborators_allowed'];
            } elseif ($event->max_collaborators_allowed !== null) {
                $data['max_collaborators_allowed'] = $event->max_collaborators_allowed;
            } else {
                $data['max_collaborators_allowed'] = $this->entitlementService->limit($user, 'collaborators.max_per_event');
            }

            if (isset($overrides['max_photos_allowed'])) {
                $data['max_photos_allowed'] = $overrides['max_photos_allowed'];
            } elseif ($event->max_photos_allowed !== null) {
                $data['max_photos_allowed'] = $event->max_photos_allowed;
            } else {
                $data['max_photos_allowed'] = $this->entitlementService->limit($user, 'photos.max_per_event');
            }

            // Copy features_enabled from original event, or set based on current subscription
            if (isset($overrides['features_enabled'])) {
                $data['features_enabled'] = $overrides['features_enabled'];
            } elseif ($event->features_enabled !== null) {
                $data['features_enabled'] = $event->features_enabled;
            } else {
                $entitlements = $this->entitlementService->getEffectiveEntitlements($user);
                $data['features_enabled'] = array_filter($entitlements['features'] ?? [], fn($value) => $value === true);
            }

            // Create the duplicated event
            $newEvent = $user->events()->create($data);

            // Optionally duplicate tasks
            if ($overrides['duplicate_tasks'] ?? true) {
                foreach ($event->tasks as $task) {
                    $newEvent->tasks()->create([
                        'title' => $task->title,
                        'description' => $task->description,
                        'priority' => $task->priority,
                        'status' => 'todo',
                        'due_date' => null,
                    ]);
                }
            }

            // Optionally duplicate budget items
            if ($overrides['duplicate_budget'] ?? true) {
                foreach ($event->budgetItems as $item) {
                    $newEvent->budgetItems()->create([
                        'category' => $item->category,
                        'name' => $item->name,
                        'estimated_cost' => $item->estimated_cost,
                        'actual_cost' => null,
                        'paid' => false,
                    ]);
                }
            }

            return $newEvent->fresh();
        });
    }

    /**
     * Apply a template to an event.
     */
    public function applyTemplate(Event $event, int $templateId): void
    {
        $template = EventTemplate::findOrFail($templateId);

        // Create default tasks from template
        $template->createTasksForEvent($event);

        // Create default budget items from template
        $template->createBudgetItemsForEvent($event);

        // Apply suggested theme if event has no theme
        if (empty($event->theme) && !empty($template->suggested_themes)) {
            $event->update(['theme' => $template->suggested_themes[0]]);
        }
    }

    /**
     * Auto-apply template based on event type.
     */
    public function autoApplyTemplate(Event $event): void
    {
        $template = EventTemplate::active()
            ->ofType($event->type)
            ->first();

        if ($template) {
            $template->createTasksForEvent($event);
            $template->createBudgetItemsForEvent($event);

            if (empty($event->theme) && !empty($template->suggested_themes)) {
                $event->update(['theme' => $template->suggested_themes[0]]);
            }
        }
    }

    /**
     * Get estimated budget by event type.
     */
    public function getEstimatedBudget(string $type): float
    {
        return $this->budgetEstimates[$type] ?? $this->budgetEstimates['autre'];
    }

    /**
     * Update event status.
     * Protects cancelled status - once cancelled, it can only be changed manually.
     */
    public function updateStatus(Event $event, EventStatus $status): Event
    {
        // Protect cancelled status - if event is cancelled, only allow changing to non-cancelled
        // If trying to change from cancelled, allow it (manual override)
        // But if trying to change a non-cancelled event to cancelled, allow it
        if ($event->status === EventStatus::CANCELLED->value && $status !== EventStatus::CANCELLED) {
            // Allow changing from cancelled to another status (manual override)
            $event->update(['status' => $status->value]);
        } elseif ($status === EventStatus::CANCELLED->value) {
            // Allow setting to cancelled (manual action)
            $event->update(['status' => $status->value]);
        } else {
            // For other status changes, allow them (automatic updates will handle the rest)
            $event->update(['status' => $status->value]);
        }

        return $event->fresh();
    }

    /**
     * Mark event as cancelled.
     */
    public function cancel(Event $event): Event
    {
        return $this->updateStatus($event, EventStatus::CANCELLED);
    }

    /**
     * Mark event as completed.
     * Note: This is mainly for manual completion. Automatic status updates will handle most cases.
     */
    public function complete(Event $event): Event
    {
        return $this->updateStatus($event, EventStatus::COMPLETED);
    }

    /**
     * Calculate actual budget from budget items.
     */
    public function recalculateActualBudget(Event $event): Event
    {
        $actualBudget = $event->budgetItems()->sum('actual_cost');
        $event->update(['actual_budget' => $actualBudget]);

        return $event->fresh();
    }

    /**
     * Get budget summary for an event.
     */
    public function getBudgetSummary(Event $event): array
    {
        $items = $event->budgetItems;

        return [
            'estimated_total' => $items->sum('estimated_cost'),
            'actual_total' => $items->sum('actual_cost'),
            'paid_total' => $items->where('paid', true)->sum('actual_cost'),
            'remaining' => $event->estimated_budget - $items->sum('actual_cost'),
            'percentage_used' => $event->estimated_budget > 0
                ? round(($items->sum('actual_cost') / $event->estimated_budget) * 100, 1)
                : 0,
            'by_category' => $items->groupBy('category')->map(function ($categoryItems) {
                return [
                    'estimated' => $categoryItems->sum('estimated_cost'),
                    'actual' => $categoryItems->sum('actual_cost'),
                    'count' => $categoryItems->count(),
                ];
            })->toArray(),
        ];
    }

    /**
     * Get event statistics.
     */
    public function getStatistics(Event $event): array
    {
        return [
            'guests' => [
                'total' => $event->guests()->count(),
                'accepted' => $event->guests()->where('rsvp_status', 'accepted')->count(),
                'declined' => $event->guests()->where('rsvp_status', 'declined')->count(),
                'pending' => $event->guests()->where('rsvp_status', 'pending')->count(),
                'maybe' => $event->guests()->where('rsvp_status', 'maybe')->count(),
                'checked_in' => $event->guests()->where('checked_in', true)->count(),
            ],
            'tasks' => [
                'total' => $event->tasks()->count(),
                'completed' => $event->tasks()->where('status', 'completed')->count(),
                'in_progress' => $event->tasks()->where('status', 'in_progress')->count(),
                'todo' => $event->tasks()->where('status', 'todo')->count(),
                'overdue' => $event->tasks()
                    ->where('status', '!=', 'completed')
                    ->where('due_date', '<', now())
                    ->count(),
            ],
            'budget' => $this->getBudgetSummary($event),
            'photos' => [
                'total' => $event->photos()->count(),
                'moodboard' => $event->photos()->where('type', 'moodboard')->count(),
                'event_photos' => $event->photos()->where('type', 'event_photo')->count(),
            ],
            'collaborators' => $event->collaborators()->count(),
            'days_until' => $event->date ? now()->diffInDays($event->date, false) : null,
        ];
    }

    /**
     * Check if user can create more events (free tier limit).
     */
    public function canCreateEvent(User $user): bool
    {
        // Admins can always create
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user has active subscription for any event
        $hasActiveSubscription = $user->subscriptions()
            ->where('payment_status', 'paid')
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasActiveSubscription) {
            return true;
        }

        // Free tier limit
        $maxEvents = config('partyplanner.free_tier.max_events', 1);
        $currentEvents = $user->events()->count();

        return $currentEvents < $maxEvents;
    }

    /**
     * Get available templates for a user.
     */
    public function getAvailableTemplates(?string $eventType = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = EventTemplate::active();

        if ($eventType) {
            $query->ofType($eventType);
        }

        return $query->get();
    }
}
