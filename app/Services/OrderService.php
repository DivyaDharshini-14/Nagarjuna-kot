<?php

namespace App\Services;

use App\Events\KotTicketChanged;
use App\Models\Kitchen;
use App\Models\KitchenMenuItem;
use App\Models\KotItem;
use App\Models\KotTicket;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OrderService
{
    /**
     * Create and submit a customer order, automatically generating kitchen-wise KOT tickets.
     */
    public function createOrder(array $data, User $waiter): Order
    {
        return DB::transaction(function () use ($data, $waiter) {
            $companyId = $data['company_id'] ?? $waiter->company_id;
            $branchId = $data['branch_id'] ?? $waiter->branch_id;

            if (! $companyId || ! $branchId) {
                throw new InvalidArgumentException('Company and Branch are required to create an order.');
            }

            $table = RestaurantTable::query()
                ->whereKey($data['table_id'])
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $hasActiveOrder = Order::query()
                ->where('table_id', $table->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->exists();
            if (strtolower($table->table_status) !== 'available' || $hasActiveOrder) {
                throw ValidationException::withMessages([
                    'table_id' => ['This table already has an active order. Refresh the floor and resume that order.'],
                ]);
            }

            if ((int) ($data['guest_count'] ?? 1) > (int) $table->seating_capacity) {
                throw ValidationException::withMessages([
                    'guest_count' => ['Guest count cannot exceed this table\'s seating capacity.'],
                ]);
            }

            $menuItems = $this->lockAndConsumePreparedStock($data['items'], (int) $companyId, (int) $branchId);
            // Serialize order-number generation within this branch.
            Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();

            $orderNumber = $this->generateOrderNumber((int) $branchId);

            $order = Order::create([
                'order_number' => $orderNumber,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'table_id' => $table->id,
                'waiter_id' => $waiter->id,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'guest_count' => $data['guest_count'] ?? 1,
                'status' => 'confirmed',
                'total_amount' => 0.00,
                'special_instructions' => $data['special_instructions'] ?? null,
            ]);

            // Update table status to occupied
            $table->update(['table_status' => 'Occupied']);

            // Create order items
            $createdOrderItems = [];
            $totalAmount = 0.00;

            foreach ($data['items'] as $itemData) {
                $menuItem = $menuItems[(int) $itemData['menu_item_id']];

                $quantity = (int) $itemData['quantity'];
                $unitPrice = (float) $menuItem->selling_price;
                $totalPrice = $unitPrice * $quantity;
                $totalAmount += $totalPrice;

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'special_instructions' => $itemData['special_instructions'] ?? null,
                    'status' => 'new',
                ]);

                $createdOrderItems[] = $orderItem;
            }

            $order->update(['total_amount' => $totalAmount]);

            // Automatically generate kitchen-wise KOT tickets
            $this->generateKitchenTickets($order, collect($createdOrderItems));

            return $order->load([
                'table',
                'waiter',
                'items.menuItem',
                'kotTickets.kitchen',
                'kotTickets.items.menuItem',
            ]);
        });
    }

    /**
     * Add additional items to an existing order and generate KOT tickets only for the new items.
     */
    public function addItemsToOrder(
        Order $order,
        array $items,
        User $waiter,
        ?string $orderInstructions = null
    ): Order
    {
        return DB::transaction(function () use ($order, $items, $waiter, $orderInstructions) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($order->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['This order is closed and cannot accept additional items.'],
                ]);
            }

            $table = RestaurantTable::query()
                ->whereKey($order->table_id)
                ->where('company_id', $order->company_id)
                ->where('branch_id', $order->branch_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if (($waiter->company_id && (int) $waiter->company_id !== (int) $order->company_id)
                || ($waiter->branch_id && (int) $waiter->branch_id !== (int) $order->branch_id)) {
                abort(403, 'This order is outside your assigned branch.');
            }

            $menuItems = $this->lockAndConsumePreparedStock($items, (int) $order->company_id, (int) $order->branch_id);
            $createdOrderItems = [];
            $addedAmount = 0.00;

            foreach ($items as $itemData) {
                $menuItem = $menuItems[(int) $itemData['menu_item_id']];

                $quantity = (int) $itemData['quantity'];
                $unitPrice = (float) $menuItem->selling_price;
                $totalPrice = $unitPrice * $quantity;
                $addedAmount += $totalPrice;

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'special_instructions' => $itemData['special_instructions'] ?? null,
                    'status' => 'new',
                ]);

                $createdOrderItems[] = $orderItem;
            }

            $order->increment('total_amount', $addedAmount);

            $newInstructions = trim((string) $orderInstructions);
            if ($newInstructions !== '') {
                $currentInstructions = trim((string) $order->special_instructions);
                $combinedInstructions = $currentInstructions === ''
                    ? $newInstructions
                    : $currentInstructions."\n".$newInstructions;

                if (mb_strlen($combinedInstructions) > 1000) {
                    throw ValidationException::withMessages([
                        'special_instructions' => ['Combined order instructions cannot exceed 1000 characters.'],
                    ]);
                }

                $order->update(['special_instructions' => $combinedInstructions]);
            }

            // Generate tickets only for the new items
            $this->generateKitchenTickets($order, collect($createdOrderItems));

            return $order->fresh([
                'table',
                'waiter',
                'items.menuItem',
                'kotTickets.kitchen',
                'kotTickets.items.menuItem',
            ]);
        });
    }

    /**
     * Lock menu rows, validate their current state, and atomically reserve tracked portions.
     *
     * @return array<int, MenuItem>
     */
    private function lockAndConsumePreparedStock(array $items, int $companyId, int $branchId): array
    {
        $quantities = [];
        foreach ($items as $item) {
            $menuItemId = (int) ($item['menu_item_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($menuItemId < 1 || $quantity < 1) {
                throw ValidationException::withMessages([
                    'items' => ['Each menu item needs a valid ID and quantity.'],
                ]);
            }
            $quantities[$menuItemId] = ($quantities[$menuItemId] ?? 0) + $quantity;
        }

        $menuItems = MenuItem::query()
            ->whereIn('id', array_keys($quantities))
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branchId))
                ->where('is_active', true))
            ->whereHas('menuType', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branchId))
                ->where('is_active', true))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($menuItems->count() !== count($quantities)) {
            throw ValidationException::withMessages([
                'items' => ['One or more dishes are no longer active for this branch. Refresh the menu and retry.'],
            ]);
        }

        foreach ($quantities as $menuItemId => $quantity) {
            /** @var MenuItem $menuItem */
            $menuItem = $menuItems->get($menuItemId);
            if (strtolower($menuItem->availability) !== 'available') {
                throw ValidationException::withMessages([
                    'items' => ["{$menuItem->name} is no longer available. Refresh the menu and retry."],
                ]);
            }

            if (! $menuItem->stock_tracking_enabled) {
                continue;
            }

            if ($menuItem->prepared_stock_quantity === null) {
                throw ValidationException::withMessages([
                    'items' => ["Stock has not been configured for {$menuItem->name}. Ask a manager to update it."],
                ]);
            }
            if ($quantity > $menuItem->prepared_stock_quantity) {
                throw ValidationException::withMessages([
                    'items' => ["Only {$menuItem->prepared_stock_quantity} portion(s) of {$menuItem->name} remain. Refresh the menu."],
                ]);
            }

            $menuItem->prepared_stock_quantity -= $quantity;
            $menuItem->save();
        }

        return $menuItems->all();
    }

    /**
     * Split order items into kitchen-wise KOT tickets using kitchen_menu_items mappings.
     */
    public function generateKitchenTickets(Order $order, Collection $orderItems): Collection
    {
        if ($orderItems->isEmpty()) {
            return collect();
        }

        // Group items by assigned kitchen
        $itemsByKitchen = [];

        foreach ($orderItems as $item) {
            // Find assigned kitchen from kitchen_menu_items
            $mapping = KitchenMenuItem::query()
                ->where('company_id', $order->company_id)
                ->where('branch_id', $order->branch_id)
                ->where('menu_item_id', $item->menu_item_id)
                ->where('is_active', true)
                ->whereHas('kitchen', fn ($query) => $query
                    ->where('company_id', $order->company_id)
                    ->where('branch_id', $order->branch_id)
                    ->where('is_active', true))
                ->first();

            $kitchenId = $mapping?->kitchen_id;

            // If no mapping, fallback to the default/first kitchen for the branch
            if (! $kitchenId) {
                $defaultKitchen = Kitchen::where('branch_id', $order->branch_id)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first();

                $kitchenId = $defaultKitchen?->id;
            }

            if (! $kitchenId) {
                throw ValidationException::withMessages([
                    'items' => ['No active kitchen is configured for this branch, so the order cannot be sent.'],
                ]);
            }

            $itemsByKitchen[$kitchenId][] = $item;
        }

        // Lock kitchens in a stable order so ticket-number generation is serialized per kitchen.
        Kitchen::query()
            ->whereIn('id', array_keys($itemsByKitchen))
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        $tickets = collect();

        foreach ($itemsByKitchen as $kitchenId => $items) {
            $kotNumber = $this->generateKotNumber((int) $kitchenId);

            $ticket = KotTicket::create([
                'kot_number' => $kotNumber,
                'order_id' => $order->id,
                'kitchen_id' => $kitchenId,
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'ticket_status' => 'new',
            ]);

            foreach ($items as $orderItem) {
                KotItem::create([
                    'kot_ticket_id' => $ticket->id,
                    'order_item_id' => $orderItem->id,
                    'menu_item_id' => $orderItem->menu_item_id,
                    'quantity' => $orderItem->quantity,
                    'special_instructions' => $orderItem->special_instructions,
                    'item_status' => 'new',
                ]);
            }

            event(new KotTicketChanged((int) $ticket->id, (int) $kitchenId, 'created'));

            $tickets->push($ticket->load(['kitchen', 'items.menuItem']));
        }

        return $tickets;
    }

    /**
     * Update the status of a KOT ticket and its items.
     */
    public function updateKotTicketStatus(KotTicket $ticket, string $status): KotTicket
    {
        return DB::transaction(function () use ($ticket, $status) {
            $updateData = ['ticket_status' => $status];

            if ($status === 'accepted') {
                $updateData['accepted_at'] = $ticket->accepted_at ?? now();
            } elseif ($status === 'preparing') {
                $updateData['accepted_at'] = $ticket->accepted_at ?? $ticket->created_at ?? now();
                $updateData['preparing_at'] = $ticket->preparing_at ?? now();
            } elseif ($status === 'ready') {
                $now = now();
                $updateData['ready_at'] = $now;
                $startTime = $ticket->accepted_at ?? $ticket->created_at ?? $now;
                $updateData['duration_seconds'] = abs((int) $now->diffInSeconds($startTime));
            }

            $ticket->update($updateData);

            // Sync item statuses
            $ticket->items()->update(['item_status' => $status]);

            // Sync parent order items
            foreach ($ticket->items as $kotItem) {
                $kotItem->orderItem?->update(['status' => $status]);
            }

            // If all KOT tickets for the order are completed / served, update order status
            $this->syncOrderStatusFromTickets($ticket->order);
            event(new KotTicketChanged((int) $ticket->id, (int) $ticket->kitchen_id, 'updated'));

            return $ticket->fresh(['kitchen', 'items.menuItem', 'order']);
        });
    }

    /**
     * Update status of an individual KOT item.
     */
    public function updateKotItemStatus(KotItem $item, string $status): KotItem
    {
        return DB::transaction(function () use ($item, $status) {
            $item->update(['item_status' => $status]);
            $item->orderItem?->update(['status' => $status]);

            // Check if all items in ticket have same status
            $ticket = $item->ticket;
            $allStatuses = $ticket->items()->pluck('item_status')->unique();

            if ($allStatuses->count() === 1) {
                $newStatus = $allStatuses->first();
                $updateData = ['ticket_status' => $newStatus];
                if ($newStatus === 'accepted') {
                    $updateData['accepted_at'] = $ticket->accepted_at ?? now();
                } elseif ($newStatus === 'preparing') {
                    $updateData['accepted_at'] = $ticket->accepted_at ?? $ticket->created_at ?? now();
                    $updateData['preparing_at'] = $ticket->preparing_at ?? now();
                } elseif ($newStatus === 'ready') {
                    $now = now();
                    $updateData['ready_at'] = $now;
                    $startTime = $ticket->accepted_at ?? $ticket->created_at ?? $now;
                    $updateData['duration_seconds'] = abs((int) $now->diffInSeconds($startTime));
                }
                $ticket->update($updateData);
            }

            $this->syncOrderStatusFromTickets($ticket->order);
            event(new KotTicketChanged((int) $ticket->id, (int) $ticket->kitchen_id, 'updated'));

            return $item->fresh(['ticket', 'orderItem', 'menuItem']);
        });
    }

    /**
     * Update order status directly (e.g. completed, cancelled).
     */
    public function updateOrderStatus(Order $order, string $status): Order
    {
        return DB::transaction(function () use ($order, $status) {
            $order->update(['status' => $status]);

            // If completed or cancelled, free the table
            if (in_array($status, ['completed', 'cancelled'], true)) {
                $order->table?->update(['table_status' => 'Available']);
            }

            return $order->fresh(['table', 'items']);
        });
    }

    protected function syncOrderStatusFromTickets(Order $order): void
    {
        $statuses = $order->kotTickets()->pluck('ticket_status')->unique();

        if ($statuses->contains('preparing')) {
            $order->update(['status' => 'preparing']);
        } elseif ($statuses->count() === 1 && $statuses->first() === 'ready') {
            $order->update(['status' => 'ready']);
        } elseif ($statuses->count() === 1 && $statuses->first() === 'served') {
            $order->update(['status' => 'served']);
        }
    }

    protected function generateOrderNumber(int $branchId): string
    {
        $prefix = 'ORD-' . date('Ymd');
        $count = Order::where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->count();

        return sprintf('%s-%04d', $prefix, $count + 1);
    }

    protected function generateKotNumber(int $kitchenId): string
    {
        $prefix = 'KOT-' . date('Ymd');
        $count = KotTicket::where('kitchen_id', $kitchenId)
            ->whereDate('created_at', today())
            ->count();

        return sprintf('%s-K%d-%04d', $prefix, $kitchenId, $count + 1);
    }
}

