<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Task $task,
        public ?string $previousStatus = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('events.' . $this->task->event_id . '.tasks'),
            new PrivateChannel('events.' . $this->task->event_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'task.status.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'event_id' => $this->task->event_id,
            'title' => $this->task->title,
            'status' => $this->task->status,
            'previous_status' => $this->previousStatus,
            'priority' => $this->task->priority,
            'assigned_to' => $this->task->assigned_to,
            'due_date' => $this->task->due_date?->toDateString(),
            'completed_at' => $this->task->completed_at?->toISOString(),
            'updated_at' => $this->task->updated_at->toISOString(),
        ];
    }
}
