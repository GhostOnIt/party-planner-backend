<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Payment $payment,
        public string $previousStatus,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('payments.' . $this->payment->id),
            new PrivateChannel('users.' . $this->payment->subscription->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.status.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'status' => $this->payment->status,
            'previous_status' => $this->previousStatus,
            'is_completed' => $this->payment->isCompleted(),
            'is_failed' => $this->payment->isFailed(),
            'transaction_reference' => $this->payment->transaction_reference,
            'amount' => $this->payment->amount,
            'updated_at' => $this->payment->updated_at->toISOString(),
        ];
    }
}
