<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'assigned_to_user_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'estimated_cost',
        'budget_category',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'estimated_cost' => 'decimal:2',
        ];
    }

    /**
     * Get the event that the task belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user assigned to the task.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get the budget item associated with this task (if any).
     */
    public function budgetItem(): HasOne
    {
        return $this->hasOne(BudgetItem::class);
    }

    /**
     * Check if the task has an associated cost.
     */
    public function hasCost(): bool
    {
        return $this->estimated_cost !== null && $this->estimated_cost > 0;
    }

    /**
     * Check if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && !$this->isCompleted();
    }

    /**
     * Check if the task is high priority.
     */
    public function isHighPriority(): bool
    {
        return $this->priority === 'high';
    }

    /**
     * Mark the task as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Reopen the task.
     */
    public function reopen(): void
    {
        $this->update([
            'status' => 'todo',
            'completed_at' => null,
        ]);
    }

    /**
     * Scope for urgent tasks (due within specified days and not completed).
     */
    public function scopeUrgent(Builder $query, int $days = 7): Builder
    {
        return $query->where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays($days))
            ->orderBy('due_date');
    }

    /**
     * Check if the task is urgent (due within 7 days and not completed).
     */
    public function isUrgent(int $days = 7): bool
    {
        return $this->due_date
            && $this->due_date->lte(now()->addDays($days))
            && !$this->isCompleted()
            && $this->status !== 'cancelled';
    }
}
