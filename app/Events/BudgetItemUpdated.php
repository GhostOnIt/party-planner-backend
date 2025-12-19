<?php

namespace App\Events;

use App\Models\BudgetItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BudgetItemUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public BudgetItem $budgetItem,
        public string $action = 'updated', // created, updated, deleted
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('events.' . $this->budgetItem->event_id . '.budget'),
            new PrivateChannel('events.' . $this->budgetItem->event_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'budget.item.' . $this->action;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'budget_item_id' => $this->budgetItem->id,
            'event_id' => $this->budgetItem->event_id,
            'action' => $this->action,
            'category' => $this->budgetItem->category,
            'name' => $this->budgetItem->name,
            'estimated_cost' => $this->budgetItem->estimated_cost,
            'actual_cost' => $this->budgetItem->actual_cost,
            'paid' => $this->budgetItem->paid,
            'updated_at' => $this->budgetItem->updated_at->toISOString(),
        ];
    }
}
