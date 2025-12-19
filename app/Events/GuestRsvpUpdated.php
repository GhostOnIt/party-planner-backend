<?php

namespace App\Events;

use App\Models\Guest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuestRsvpUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Guest $guest,
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
            new PrivateChannel('events.' . $this->guest->event_id . '.guests'),
            new PrivateChannel('events.' . $this->guest->event_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'guest.rsvp.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'guest_id' => $this->guest->id,
            'event_id' => $this->guest->event_id,
            'name' => $this->guest->name,
            'rsvp_status' => $this->guest->rsvp_status,
            'previous_status' => $this->previousStatus,
            'plus_one' => $this->guest->plus_one,
            'plus_one_name' => $this->guest->plus_one_name,
            'checked_in_at' => $this->guest->checked_in_at?->toISOString(),
            'updated_at' => $this->guest->updated_at->toISOString(),
        ];
    }
}
