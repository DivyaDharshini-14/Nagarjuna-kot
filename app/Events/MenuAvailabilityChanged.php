<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MenuAvailabilityChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $branchId,
        public readonly int $menuItemId,
        public readonly string $availability,
        public readonly ?int $preparedStockQuantity,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('branches.'.$this->branchId.'.menu')];
    }

    public function broadcastAs(): string
    {
        return 'menu.item.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'menu_item_id' => $this->menuItemId,
            'availability' => $this->availability,
            'prepared_stock_quantity' => $this->preparedStockQuantity,
        ];
    }
}
