<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KotTicketChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $ticketId,
        public readonly int $kitchenId,
        public readonly string $changeType,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('kitchens.'.$this->kitchenId)];
    }

    public function broadcastAs(): string
    {
        return 'kot.ticket.'.$this->changeType;
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'kitchen_id' => $this->kitchenId,
            'change_type' => $this->changeType,
        ];
    }
}
