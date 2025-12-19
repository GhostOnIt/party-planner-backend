<?php

namespace App\Jobs;

use App\Mail\TaskReminderMail;
use App\Models\Notification;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TaskReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Task $task,
        public int $daysUntilDue
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if task is still relevant
        if ($this->task->isCompleted()) {
            Log::info("TaskReminderJob: Task {$this->task->id} is already completed, skipping reminder");
            return;
        }

        if (!$this->task->assigned_to_user_id) {
            Log::warning("TaskReminderJob: Task {$this->task->id} has no assignee, skipping reminder");
            return;
        }

        $assignee = $this->task->assignedUser;

        if (!$assignee) {
            Log::warning("TaskReminderJob: Assignee not found for task {$this->task->id}");
            return;
        }

        try {
            // Create in-app notification
            $this->createNotification();

            // Send email if assignee has email
            if ($assignee->email) {
                Mail::to($assignee->email)
                    ->send(new TaskReminderMail($this->task, $this->daysUntilDue));
            }

            Log::info("TaskReminderJob: Reminder sent for task {$this->task->id} to user {$assignee->id}");
        } catch (\Exception $e) {
            Log::error("TaskReminderJob: Failed to send reminder for task {$this->task->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create in-app notification.
     */
    protected function createNotification(): void
    {
        $message = $this->daysUntilDue === 0
            ? "La tâche \"{$this->task->title}\" est due aujourd'hui !"
            : "La tâche \"{$this->task->title}\" est due dans {$this->daysUntilDue} jour(s).";

        Notification::create([
            'user_id' => $this->task->assigned_to_user_id,
            'event_id' => $this->task->event_id,
            'type' => 'task_reminder',
            'title' => 'Rappel de tâche',
            'message' => $message,
            'sent_via' => 'database',
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("TaskReminderJob: Job failed for task {$this->task->id}: " . $exception->getMessage());
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'task-reminder',
            'task:' . $this->task->id,
            'event:' . $this->task->event_id,
        ];
    }
}
