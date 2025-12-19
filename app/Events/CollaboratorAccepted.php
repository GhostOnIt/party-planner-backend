<?php

namespace App\Events;

use App\Models\Collaborator;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CollaboratorAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Collaborator $collaborator,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('events.' . $this->collaborator->event_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'collaborator.accepted';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->collaborator->load('user');

        return [
            'collaborator_id' => $this->collaborator->id,
            'event_id' => $this->collaborator->event_id,
            'user' => [
                'id' => $this->collaborator->user->id,
                'name' => $this->collaborator->user->name,
                'avatar_url' => $this->collaborator->user->avatar_url,
            ],
            'role' => $this->collaborator->role,
            'accepted_at' => $this->collaborator->accepted_at->toISOString(),
        ];
    }
}
