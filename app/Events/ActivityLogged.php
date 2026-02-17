<?php

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityLogged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ActivityLog $activityLog,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.activity'),
            new PrivateChannel('admin'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'activity.logged';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->activityLog->load('user');

        return [
            'id' => $this->activityLog->id,
            'action' => $this->activityLog->action,
            'description' => $this->activityLog->description,
            'model_type' => $this->activityLog->model_type,
            'model_id' => $this->activityLog->model_id,
            'actor_type' => $this->activityLog->actor_type,
            'source' => $this->activityLog->source,
            'user' => [
                'id' => $this->activityLog->user->id,
                'name' => $this->activityLog->user->name,
            ],
            'created_at' => $this->activityLog->created_at->toISOString(),
        ];
    }
}
