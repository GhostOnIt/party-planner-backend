<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTemplate;
use Illuminate\Support\Collection;

class EventTemplateService
{
    /**
     * Get all active templates.
     */
    public function getActiveTemplates(): Collection
    {
        return EventTemplate::active()
            ->orderBy('event_type')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get templates by event type.
     */
    public function getTemplatesByType(string $eventType): Collection
    {
        return EventTemplate::active()
            ->ofType($eventType)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get template by ID.
     */
    public function getTemplate(int $id): ?EventTemplate
    {
        return EventTemplate::find($id);
    }

    /**
     * Apply template to an event.
     */
    public function applyToEvent(EventTemplate $template, Event $event, array $options = []): array
    {
        $results = [
            'tasks_created' => 0,
            'budget_items_created' => 0,
            'theme_applied' => false,
        ];

        // Apply tasks
        if ($options['apply_tasks'] ?? true) {
            $results['tasks_created'] = $this->applyTasks($template, $event);
        }

        // Apply budget items
        if ($options['apply_budget'] ?? true) {
            $results['budget_items_created'] = $this->applyBudgetItems($template, $event);
        }

        // Apply theme
        if (($options['apply_theme'] ?? false) && !empty($template->suggested_themes)) {
            $theme = $options['theme'] ?? $template->suggested_themes[0] ?? null;
            if ($theme) {
                $event->update(['theme' => $theme]);
                $results['theme_applied'] = true;
            }
        }

        return $results;
    }

    /**
     * Apply tasks from template to event.
     */
    public function applyTasks(EventTemplate $template, Event $event): int
    {
        if (empty($template->default_tasks)) {
            return 0;
        }

        $count = 0;

        foreach ($template->default_tasks as $task) {
            $taskData = is_array($task) ? $task : ['title' => $task];

            // Calculate due date if days_before_event is specified
            $dueDate = null;
            if (isset($taskData['days_before_event']) && $event->date) {
                $dueDate = $event->date->copy()->subDays($taskData['days_before_event']);
            }

            $event->tasks()->create([
                'title' => $taskData['title'],
                'description' => $taskData['description'] ?? null,
                'priority' => $taskData['priority'] ?? 'medium',
                'status' => 'todo',
                'due_date' => $dueDate,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Apply budget items from template to event.
     */
    public function applyBudgetItems(EventTemplate $template, Event $event): int
    {
        if (empty($template->default_budget_categories)) {
            return 0;
        }

        $count = 0;

        foreach ($template->default_budget_categories as $item) {
            $itemData = is_array($item) ? $item : ['name' => $item, 'category' => 'other'];

            $event->budgetItems()->create([
                'category' => $itemData['category'] ?? 'other',
                'name' => $itemData['name'],
                'estimated_cost' => $itemData['estimated_cost'] ?? 0,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Get suggested themes for an event type.
     */
    public function getSuggestedThemes(string $eventType): array
    {
        $templates = $this->getTemplatesByType($eventType);

        $themes = [];
        foreach ($templates as $template) {
            if (!empty($template->suggested_themes)) {
                $themes = array_merge($themes, $template->suggested_themes);
            }
        }

        return array_unique($themes);
    }

    /**
     * Preview template application without applying.
     */
    public function previewApplication(EventTemplate $template): array
    {
        return [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'event_type' => $template->event_type,
                'description' => $template->description,
            ],
            'tasks' => $this->formatTasks($template->default_tasks ?? []),
            'budget_items' => $this->formatBudgetItems($template->default_budget_categories ?? []),
            'themes' => $template->suggested_themes ?? [],
            'summary' => [
                'tasks_count' => count($template->default_tasks ?? []),
                'budget_items_count' => count($template->default_budget_categories ?? []),
                'themes_count' => count($template->suggested_themes ?? []),
            ],
        ];
    }

    /**
     * Format tasks for preview.
     */
    protected function formatTasks(array $tasks): array
    {
        return collect($tasks)->map(function ($task) {
            if (is_string($task)) {
                return ['title' => $task, 'priority' => 'medium'];
            }

            return [
                'title' => $task['title'] ?? 'Tâche sans titre',
                'description' => $task['description'] ?? null,
                'priority' => $task['priority'] ?? 'medium',
                'days_before_event' => $task['days_before_event'] ?? null,
            ];
        })->toArray();
    }

    /**
     * Format budget items for preview.
     */
    protected function formatBudgetItems(array $items): array
    {
        return collect($items)->map(function ($item) {
            if (is_string($item)) {
                return ['name' => $item, 'category' => 'other', 'estimated_cost' => 0];
            }

            return [
                'name' => $item['name'] ?? 'Item sans nom',
                'category' => $item['category'] ?? 'other',
                'estimated_cost' => $item['estimated_cost'] ?? 0,
            ];
        })->toArray();
    }

    /**
     * Get templates grouped by event type.
     */
    public function getTemplatesGroupedByType(): Collection
    {
        return EventTemplate::active()
            ->orderBy('event_type')
            ->orderBy('name')
            ->get()
            ->groupBy('event_type');
    }

    /**
     * Create a custom template from an event.
     */
    public function createFromEvent(Event $event, string $name, ?string $description = null): EventTemplate
    {
        $tasks = $event->tasks->map(function ($task) use ($event) {
            $data = [
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
            ];

            if ($task->due_date && $event->date) {
                $data['days_before_event'] = $event->date->diffInDays($task->due_date);
            }

            return $data;
        })->toArray();

        $budgetItems = $event->budgetItems->map(function ($item) {
            return [
                'name' => $item->name,
                'category' => $item->category,
                'estimated_cost' => $item->estimated_cost,
            ];
        })->toArray();

        return EventTemplate::create([
            'event_type' => $event->type,
            'name' => $name,
            'description' => $description ?? "Template créé depuis l'événement \"{$event->title}\"",
            'default_tasks' => $tasks,
            'default_budget_categories' => $budgetItems,
            'suggested_themes' => $event->theme ? [$event->theme] : [],
            'is_active' => true,
        ]);
    }
}
