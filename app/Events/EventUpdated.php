<?php

namespace App\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Event $event,
        public ?User $updatedBy = null,
        public array $changedFields = [],
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('events.' . $this->event->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'event.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->event->id,
            'title' => $this->event->title,
            'status' => $this->event->status,
            'date' => $this->event->date?->toDateString(),
            'time' => $this->event->time,
            'location' => $this->event->location,
            'changed_fields' => $this->changedFields,
            'updated_by' => $this->updatedBy ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ] : null,
            'updated_at' => $this->event->updated_at->toISOString(),
        ];
    }
}
