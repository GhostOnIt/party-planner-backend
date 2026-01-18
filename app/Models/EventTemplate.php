<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTemplate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_type',
        'name',
        'description',
        'default_tasks',
        'default_budget_categories',
        'suggested_themes',
        'cover_photo_url',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_tasks' => 'array',
            'default_budget_categories' => 'array',
            'suggested_themes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by event type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Get the event type label.
     */
    public function getEventTypeLabelAttribute(): string
    {
        return match ($this->event_type) {
            'mariage' => 'Mariage',
            'anniversaire' => 'Anniversaire',
            'baby_shower' => 'Baby Shower',
            'soiree' => 'Soirée',
            'brunch' => 'Brunch',
            'autre' => 'Autre',
            default => $this->event_type,
        };
    }

    /**
     * Create default tasks for an event based on this template.
     */
    public function createTasksForEvent(Event $event): void
    {
        if (empty($this->default_tasks)) {
            return;
        }

        foreach ($this->default_tasks as $task) {
            $event->tasks()->create([
                'title' => $task['title'] ?? $task,
                'description' => $task['description'] ?? null,
                'priority' => $task['priority'] ?? 'medium',
                'status' => 'todo',
            ]);
        }
    }

    /**
     * Create default budget items for an event based on this template.
     */
    public function createBudgetItemsForEvent(Event $event): void
    {
        if (empty($this->default_budget_categories)) {
            return;
        }

        foreach ($this->default_budget_categories as $item) {
            $event->budgetItems()->create([
                'category' => $item['category'] ?? 'other',
                'name' => $item['name'] ?? $item,
                'estimated_cost' => $item['estimated_cost'] ?? null,
            ]);
        }
    }
}
