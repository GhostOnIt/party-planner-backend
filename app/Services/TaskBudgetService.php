<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\Task;

class TaskBudgetService
{
    /**
     * Synchronize budget item from task.
     * Creates or updates a budget item when a task has a cost.
     */
    public function syncBudgetItemFromTask(Task $task): ?BudgetItem
    {
        // If task has no cost, remove associated budget item if exists
        if (!$task->hasCost()) {
            $this->removeBudgetItemFromTask($task);
            return null;
        }

        // Get or create budget item for this task
        $budgetItem = BudgetItem::firstOrNew(['task_id' => $task->id]);

        // Update budget item data from task
        $budgetItem->event_id = $task->event_id;
        $budgetItem->name = $task->title;
        $budgetItem->estimated_cost = $task->estimated_cost;
        $budgetItem->category = $task->budget_category ?? 'other';
        
        // Preserve existing actual_cost and paid status if budget item already exists
        // Only update if budget item is new
        if (!$budgetItem->exists) {
            $budgetItem->actual_cost = null;
            $budgetItem->paid = false;
            $budgetItem->payment_date = null;
        }

        // Use task description as notes if available
        if ($task->description && !$budgetItem->notes) {
            $budgetItem->notes = $task->description;
        }

        $budgetItem->save();

        return $budgetItem;
    }

    /**
     * Remove budget item associated with a task.
     */
    public function removeBudgetItemFromTask(Task $task): bool
    {
        $budgetItem = BudgetItem::where('task_id', $task->id)->first();
        
        if ($budgetItem) {
            return $budgetItem->delete();
        }

        return false;
    }

    /**
     * Update budget item when task is updated.
     */
    public function updateBudgetItemFromTask(Task $task, array $changedAttributes = []): ?BudgetItem
    {
        $budgetItem = BudgetItem::where('task_id', $task->id)->first();

        // If task no longer has cost, remove budget item
        if (!$task->hasCost()) {
            if ($budgetItem) {
                $budgetItem->delete();
            }
            return null;
        }

        // If budget item doesn't exist but task has cost, create it
        if (!$budgetItem) {
            return $this->syncBudgetItemFromTask($task);
        }

        // Update only changed attributes
        $shouldUpdate = false;

        if (isset($changedAttributes['title']) || isset($changedAttributes['estimated_cost'])) {
            $budgetItem->name = $task->title;
            $budgetItem->estimated_cost = $task->estimated_cost;
            $shouldUpdate = true;
        }

        if (isset($changedAttributes['budget_category'])) {
            $budgetItem->category = $task->budget_category ?? 'other';
            $shouldUpdate = true;
        }

        if (isset($changedAttributes['description']) && $task->description) {
            // Only update notes if they're empty or if description changed
            if (!$budgetItem->notes || isset($changedAttributes['description'])) {
                $budgetItem->notes = $task->description;
                $shouldUpdate = true;
            }
        }

        if ($shouldUpdate) {
            $budgetItem->save();
        }

        return $budgetItem;
    }

    /**
     * Check if a task should create a budget item.
     */
    public function shouldCreateBudgetItem(Task $task): bool
    {
        return $task->hasCost();
    }
}
