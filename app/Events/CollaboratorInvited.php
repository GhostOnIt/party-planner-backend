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

class CollaboratorInvited implements ShouldBroadcast
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
        $channels = [
            new PrivateChannel('events.' . $this->collaborator->event_id),
        ];

        // Also notify the invited user
        if ($this->collaborator->user_id) {
            $channels[] = new PrivateChannel('users.' . $this->collaborator->user_id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'collaborator.invited';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->collaborator->load(['user', 'event']);

        return [
            'collaborator_id' => $this->collaborator->id,
            'event_id' => $this->collaborator->event_id,
            'event_title' => $this->collaborator->event->title,
            'user' => $this->collaborator->user ? [
                'id' => $this->collaborator->user->id,
                'name' => $this->collaborator->user->name,
                'email' => $this->collaborator->user->email,
            ] : null,
            'email' => $this->collaborator->email,
            'role' => $this->collaborator->role,
            'created_at' => $this->collaborator->created_at->toISOString(),
        ];
    }
}
