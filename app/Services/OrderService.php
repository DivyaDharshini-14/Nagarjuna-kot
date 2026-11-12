<?php

namespace App\Services;

use App\Events\KotTicketChanged;
use App\Events\MenuAvailabilityChanged;
use App\Models\Kitchen;
use App\Models\KitchenMenuItem;
use App\Models\ItemCancellation;
use App\Models\KotItem;
use App\Models\KotTicket;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

            $this->recordOrderStockDeductions($order, $data['items'], $waiter, $menuItems);

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
            $this->recordOrderStockDeductions($order, $items, $waiter, $menuItems);
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
            $this->syncOrderStatusFromTickets($order);

            return $order->fresh([
                'table',
                'waiter',
                'items.menuItem',
                'kotTickets.kitchen',
                'kotTickets.items.menuItem',
            ]);
        });
    }

    /** Update an order line's kitchen instructions before preparation begins. */
    public function updateItemInstructions(
        Order $order,
        OrderItem $orderItem,
        ?string $instructions,
        User $actor,
    ): Order {
        return DB::transaction(function () use ($order, $orderItem, $instructions, $actor) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedOrder->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['This order is closed and cannot be modified.'],
                ]);
            }
            if ((int) $actor->company_id !== (int) $lockedOrder->company_id
                || (int) $actor->branch_id !== (int) $lockedOrder->branch_id) {
                abort(404);
            }

            $lockedItem = OrderItem::query()
                ->where('order_id', $lockedOrder->id)
                ->whereKey($orderItem->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedItem->cancelled_quantity >= $lockedItem->quantity
                || $lockedItem->cancellations()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'item' => ['This dish cannot be modified while cancelled or awaiting a cancellation decision.'],
                ]);
            }

            $kotItems = KotItem::query()
                ->where('order_item_id', $lockedItem->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($kotItems->isEmpty() || $kotItems->contains(fn (KotItem $item) =>
                ! in_array(strtolower((string) $item->item_status), ['new', 'accepted'], true)
            )) {
                throw ValidationException::withMessages([
                    'item' => ['Dish instructions can only be changed before preparation starts.'],
                ]);
            }

            $lockedItem->update(['special_instructions' => $instructions]);
            foreach ($kotItems as $kotItem) {
                $kotItem->update(['special_instructions' => $instructions]);
                $ticket = KotTicket::query()->find($kotItem->kot_ticket_id);
                if ($ticket) {
                    event(new KotTicketChanged(
                        (int) $ticket->id,
                        (int) $ticket->kitchen_id,
                        (int) $ticket->order_id,
                        'updated',
                        (string) $ticket->ticket_status,
                    ));
                }
            }

            return $lockedOrder->fresh([
                'table', 'waiter', 'items.menuItem', 'items.cancellations',
                'kotTickets.kitchen', 'kotTickets.items.menuItem',
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

    /** Record the single stock movement created by the order transaction. */
    private function recordOrderStockDeductions(Order $order, array $items, User $actor, array $menuItems): void
    {
        $quantities = [];
        foreach ($items as $item) {
            $menuItemId = (int) $item['menu_item_id'];
            $quantities[$menuItemId] = ($quantities[$menuItemId] ?? 0) + (int) $item['quantity'];
        }

        foreach ($quantities as $menuItemId => $quantity) {
            /** @var MenuItem|null $menuItem */
            $menuItem = $menuItems[$menuItemId] ?? null;
            if (! $menuItem || ! $menuItem->stock_tracking_enabled) {
                continue;
            }

            $after = (int) $menuItem->prepared_stock_quantity;
            StockTransaction::create([
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'menu_item_id' => $menuItem->id,
                'user_id' => $actor->id,
                'order_id' => $order->id,
                'transaction_type' => 'order_deduction',
                'quantity' => $quantity,
                'quantity_delta' => -$quantity,
                'stock_before' => $after + $quantity,
                'stock_after' => $after,
                'reason' => 'Stock reserved for '.$order->order_number,
            ]);
            event(new MenuAvailabilityChanged(
                (int) $menuItem->branch_id,
                (int) $menuItem->id,
                (string) $menuItem->availability,
                $menuItem->prepared_stock_quantity === null ? null : (int) $menuItem->prepared_stock_quantity,
            ));
        }
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

            event(new KotTicketChanged(
                (int) $ticket->id,
                (int) $kitchenId,
                (int) $order->id,
                'created',
                'new',
            ));

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
            event(new KotTicketChanged(
                (int) $ticket->id,
                (int) $ticket->kitchen_id,
                (int) $ticket->order_id,
                'updated',
                $status,
            ));

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
            event(new KotTicketChanged(
                (int) $ticket->id,
                (int) $ticket->kitchen_id,
                (int) $ticket->order_id,
                'updated',
                (string) $ticket->fresh()->ticket_status,
            ));

            return $item->fresh(['ticket', 'orderItem', 'menuItem']);
        });
    }

    /** Mark a fully ready order line served across every kitchen ticket that contains it. */
    public function markMobileOrderItemServed(Order $order, OrderItem $item): Order
    {
        return DB::transaction(function () use ($order, $item) {
            KotTicket::query()
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if(in_array($lockedOrder->status, ['completed', 'cancelled'], true), 409, 'This order is already closed.');

            $lockedItem = OrderItem::query()
                ->where('order_id', $lockedOrder->id)
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if($lockedItem->cancelled_quantity >= $lockedItem->quantity, 409, 'This dish has already been cancelled.');
            abort_if(
                ItemCancellation::query()->where('order_item_id', $lockedItem->id)->where('status', 'pending')->exists(),
                409,
                'Resolve the pending cancellation before marking this dish served.',
            );

            $kotItems = KotItem::query()
                ->where('order_item_id', $lockedItem->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            abort_unless($kotItems->isNotEmpty(), 409, 'This dish has no kitchen ticket.');
            if ($kotItems->every(fn (KotItem $kotItem) => strtolower((string) $kotItem->item_status) === 'served')) {
                return $lockedOrder->fresh([
                    'table',
                    'items.menuItem',
                    'items.kotItems',
                    'items.cancellations.requester',
                    'items.cancellations.decider',
                    'kotTickets.kitchen',
                    'kotTickets.items.menuItem',
                ]);
            }
            abort_unless(
                $kotItems->every(fn (KotItem $kotItem) => strtolower((string) $kotItem->item_status) === 'ready'),
                409,
                'Every kitchen handling this dish must mark it ready before it can be served.',
            );

            $kotItems->each(fn (KotItem $kotItem) => $kotItem->update(['item_status' => 'served']));
            $lockedItem->update(['status' => 'served']);

            foreach ($kotItems->pluck('kot_ticket_id')->unique() as $ticketId) {
                $ticket = KotTicket::query()->whereKey($ticketId)->lockForUpdate()->firstOrFail();
                $activeStatuses = KotItem::query()
                    ->where('kot_ticket_id', $ticket->id)
                    ->whereColumn('cancelled_quantity', '<', 'quantity')
                    ->pluck('item_status')
                    ->map(fn ($status) => strtolower((string) $status));

                $ticketStatus = $activeStatuses->isEmpty()
                    ? 'cancelled'
                    : ($activeStatuses->every(fn ($status) => in_array($status, ['ready', 'served'], true))
                        ? ($activeStatuses->contains('ready') ? 'ready' : 'served')
                        : null);

                if ($ticketStatus !== null && $ticket->ticket_status !== $ticketStatus) {
                    $ticket->update(['ticket_status' => $ticketStatus]);
                }
                event(new KotTicketChanged(
                    (int) $ticket->id,
                    (int) $ticket->kitchen_id,
                    (int) $ticket->order_id,
                    'updated',
                    (string) ($ticketStatus ?? $ticket->ticket_status),
                ));
            }

            $this->syncOrderStatusFromTickets($lockedOrder);

            return $lockedOrder->fresh([
                'table',
                'items.menuItem',
                'items.kotItems',
                'items.cancellations.requester',
                'items.cancellations.decider',
                'kotTickets.kitchen',
                'kotTickets.items.menuItem',
            ]);
        });
    }

    /**
     * Update order status directly (e.g. completed, cancelled).
     */
    public function updateOrderStatus(Order $order, string $status): Order
    {
        abort_unless($status !== 'cancelled', 403, 'Use the approved cancellation workflow to cancel an order.');
        return DB::transaction(function () use ($order, $status) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($status === 'completed' && $lockedOrder->itemCancellations()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'order' => ['Pending cancellation requests must be decided before this order can be completed.'],
                ]);
            }
            $lockedOrder->update(['status' => $status]);

            // If completed or cancelled, free the table
            if (in_array($status, ['completed', 'cancelled'], true)) {
                $lockedOrder->table?->update(['table_status' => 'Available']);
            }

            return $lockedOrder->fresh(['table', 'items']);
        });
    }

    /**
     * Apply a reasoned cancellation immediately unless the branch requires
     * manager approval. order.cancel authorizes the action; order.approve
     * authorizes deciding requests when branch approval is enabled.
     */
    public function requestItemCancellations(
        Order $order,
        array $requestedItems,
        string $reason,
        bool $cancelAll,
        User $requester,
    ): array {
        return DB::transaction(function () use ($order, $requestedItems, $reason, $cancelAll, $requester) {
            KotTicket::query()
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedOrder->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['This order is closed and cannot be cancelled.'],
                ]);
            }

            $orderItems = OrderItem::query()
                ->where('order_id', $lockedOrder->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $requestedQuantities = [];
            if ($cancelAll) {
                foreach ($orderItems as $item) {
                    $pending = (int) $item->cancellations()->where('status', 'pending')->sum('quantity');
                    $available = $item->quantity - $item->cancelled_quantity - $pending;
                    if ($available > 0) $requestedQuantities[$item->id] = $available;
                }
            } else {
                foreach ($requestedItems as $line) {
                    $itemId = (int) ($line['order_item_id'] ?? 0);
                    $quantity = (int) ($line['quantity'] ?? 0);
                    $requestedQuantities[$itemId] = ($requestedQuantities[$itemId] ?? 0) + $quantity;
                }
            }

            if ($requestedQuantities === []) {
                throw ValidationException::withMessages([
                    'items' => ['There are no uncancelled quantities available to request.'],
                ]);
            }

            $batch = (string) Str::uuid();
            $approvalRequired = (bool) Branch::query()
                ->whereKey($lockedOrder->branch_id)
                ->value('cancellation_approval_required');
            $autoApprove = ! $approvalRequired;
            $created = collect();

            foreach ($requestedQuantities as $itemId => $quantity) {
                /** @var OrderItem|null $item */
                $item = $orderItems->firstWhere('id', $itemId);
                if (! $item) {
                    throw ValidationException::withMessages([
                        'items' => ['A selected item does not belong to this order.'],
                    ]);
                }

                $pending = (int) $item->cancellations()->where('status', 'pending')->sum('quantity');
                $available = $item->quantity - $item->cancelled_quantity - $pending;
                if ($quantity < 1 || $quantity > $available) {
                    throw ValidationException::withMessages([
                        'items' => ["The requested cancellation quantity for {$item->menuItem()->value('name')} is no longer available."],
                    ]);
                }
                if (in_array(strtolower($item->status), ['cancelled', 'completed'], true)) {
                    throw ValidationException::withMessages([
                        'items' => ['A completed or cancelled item cannot be cancelled again.'],
                    ]);
                }

                $created->push(ItemCancellation::create([
                    'request_batch' => $batch,
                    'company_id' => $lockedOrder->company_id,
                    'branch_id' => $lockedOrder->branch_id,
                    'order_id' => $lockedOrder->id,
                    'order_item_id' => $item->id,
                    'quantity' => $quantity,
                    'reason' => $reason,
                    'status' => $autoApprove ? 'approved' : 'pending',
                    'requested_by' => $requester->id,
                    'decided_by' => $autoApprove ? $requester->id : null,
                    'decided_at' => $autoApprove ? now() : null,
                ]));
            }

            if ($autoApprove) {
                $this->applyCancellationBatch($batch, $requester);
            }

            return [$batch, $created->map(fn (ItemCancellation $record) =>
                $record->fresh(['orderItem.menuItem', 'requester'])
            )];
        });
    }

    /** Approve or reject all pending line cancellations in one request batch. */
    public function decideCancellationBatch(string $batch, User $actor, bool $approve, ?string $note = null): void
    {
        abort_unless($actor->hasPermission('order.approve'), 403);
        DB::transaction(function () use ($batch, $actor, $approve, $note) {
            $records = ItemCancellation::query()
                ->where('request_batch', $batch)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            abort_if($records->isEmpty(), 404);
            abort_unless($records->every(fn (ItemCancellation $record) =>
                (! $actor->company_id || (int) $record->company_id === (int) $actor->company_id)
                && (! $actor->branch_id || (int) $record->branch_id === (int) $actor->branch_id)
            ), 404);
            abort_unless($records->every(fn (ItemCancellation $record) => $record->status === 'pending'), 409);

            $approvalRequired = (bool) Branch::query()
                ->whereKey($records->first()->branch_id)
                ->value('cancellation_approval_required');
            if ($approve && $approvalRequired) {
                abort_unless($records->every(fn (ItemCancellation $record) =>
                    (int) $record->requested_by !== (int) $actor->id
                ), 403, 'A cancellation requester cannot approve their own request for this branch.');
            }

            if (! $approve) {
                ItemCancellation::query()->where('request_batch', $batch)->update([
                    'status' => 'rejected',
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'decision_note' => $note,
                    'updated_at' => now(),
                ]);
                return;
            }

            ItemCancellation::query()->where('request_batch', $batch)->update([
                'status' => 'approved',
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'updated_at' => now(),
            ]);
            $this->applyCancellationBatch($batch, $actor);
        });
    }

    private function applyCancellationBatch(string $batch, User $actor): void
    {
        $records = ItemCancellation::query()
            ->where('request_batch', $batch)
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();
        $firstRecord = $records->firstOrFail();
        KotTicket::query()
            ->where('order_id', $firstRecord->order_id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
        $order = Order::query()->whereKey($firstRecord->order_id)->lockForUpdate()->firstOrFail();
        abort_if(in_array($order->status, ['completed', 'cancelled'], true), 409);

        $changedTickets = [];
        foreach ($records as $record) {
            $item = OrderItem::query()->whereKey($record->order_item_id)->lockForUpdate()->firstOrFail();
            if ($item->cancelled_quantity + $record->quantity > $item->quantity) {
                throw ValidationException::withMessages(['items' => ['The order item quantity changed before approval.']]);
            }

            $kotItems = KotItem::query()
                ->where('order_item_id', $item->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $remaining = (int) $record->quantity;
            $stockReversedQuantity = 0;
            $wastageQuantity = 0;
            $kotItems = $kotItems->sortBy(fn (KotItem $kotItem) =>
                in_array(strtolower((string) $kotItem->item_status), ['new', 'accepted'], true) ? 0 : 1
            )->values();
            foreach ($kotItems as $kotItem) {
                if ($remaining <= 0) break;
                $available = $kotItem->quantity - $kotItem->cancelled_quantity;
                $cancelQuantity = min($available, $remaining);
                if ($cancelQuantity > 0) {
                    $kotItem->increment('cancelled_quantity', $cancelQuantity);
                    $changedTickets[$kotItem->kot_ticket_id] = true;
                    if (in_array(strtolower((string) $kotItem->item_status), ['new', 'accepted'], true)) {
                        $stockReversedQuantity += $cancelQuantity;
                    } else {
                        $wastageQuantity += $cancelQuantity;
                    }
                    $remaining -= $cancelQuantity;
                }
            }
            if ($remaining > 0) {
                throw ValidationException::withMessages(['items' => ['The KOT quantity no longer matches this order item.']]);
            }

            $item->cancelled_quantity += $record->quantity;
            if ($item->cancelled_quantity >= $item->quantity) $item->status = 'cancelled';
            $amountCents = (int) round((int) $record->quantity * (float) $item->unit_price * 100);
            $remainingLineCents = max(0, (int) round((float) $item->total_price * 100) - $amountCents);
            $item->total_price = number_format($remainingLineCents / 100, 2, '.', '');
            $item->save();
            $order->decrement('total_amount', $amountCents / 100);

            $menuItem = MenuItem::query()->whereKey($item->menu_item_id)->lockForUpdate()->firstOrFail();
            $stockBefore = (int) ($menuItem->prepared_stock_quantity ?? 0);
            if ($menuItem->stock_tracking_enabled && $menuItem->prepared_stock_quantity !== null && $stockReversedQuantity > 0) {
                $menuItem->prepared_stock_quantity += $stockReversedQuantity;
                $menuItem->save();
                StockTransaction::create([
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                    'menu_item_id' => $menuItem->id,
                    'user_id' => $actor->id,
                    'order_id' => $order->id,
                    'item_cancellation_id' => $record->id,
                    'transaction_type' => 'order_cancellation_reversal',
                    'quantity' => $stockReversedQuantity,
                    'quantity_delta' => $stockReversedQuantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $menuItem->prepared_stock_quantity,
                    'reason' => $record->reason,
                ]);
                event(new MenuAvailabilityChanged(
                    (int) $menuItem->branch_id,
                    (int) $menuItem->id,
                    (string) $menuItem->availability,
                    (int) $menuItem->prepared_stock_quantity,
                ));
            }
            if ($wastageQuantity > 0) {
                $currentStock = (int) ($menuItem->prepared_stock_quantity ?? 0);
                StockTransaction::create([
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                    'menu_item_id' => $menuItem->id,
                    'user_id' => $actor->id,
                    'order_id' => $order->id,
                    'item_cancellation_id' => $record->id,
                    'transaction_type' => 'wastage',
                    'quantity' => $wastageQuantity,
                    'quantity_delta' => 0,
                    'stock_before' => $currentStock,
                    'stock_after' => $currentStock,
                    'reason' => 'Cancellation after preparation started: '.$record->reason,
                ]);
            }
            $record->update([
                'stock_reversed' => $stockReversedQuantity === (int) $record->quantity,
                'stock_reversed_quantity' => $stockReversedQuantity,
                'wastage_quantity' => $wastageQuantity,
            ]);
        }

        foreach (array_keys($changedTickets) as $ticketId) {
            $ticket = KotTicket::query()->whereKey($ticketId)->lockForUpdate()->first();
            if (! $ticket) continue;
            $allCancelled = ! $ticket->items()->whereColumn('cancelled_quantity', '<', 'quantity')->exists();
            if ($allCancelled) $ticket->update(['ticket_status' => 'cancelled']);
            event(new KotTicketChanged((int) $ticket->id, (int) $ticket->kitchen_id, (int) $ticket->order_id, 'cancelled', (string) $ticket->ticket_status));
        }

        $this->syncOrderStatusFromTickets($order);

        $allItemsCancelled = ! $order->items()->whereColumn('cancelled_quantity', '<', 'quantity')->exists();
        if ($allItemsCancelled) {
            $order->update(['status' => 'cancelled']);
            $table = RestaurantTable::query()->whereKey($order->table_id)->lockForUpdate()->first();
            if ($table) {
                $anotherActiveOrder = Order::query()
                    ->where('table_id', $table->id)
                    ->where('id', '<>', $order->id)
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->exists();
                if (! $anotherActiveOrder) $table->update(['table_status' => 'Available']);
            }
        }
    }

    /**
     * Complete an order after active kitchen tickets are ready; cancelled tickets are terminal.
     * The order and table are locked so a second waiter cannot reopen the table
     * while this completion is being committed.
     */
    public function completeMobileOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // Lock tickets before the parent order: KOT updates also lock a
            // ticket before updating the order status.
            $tickets = KotTicket::query()
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'kitchen_id', 'ticket_status']);

            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === 'completed') {
                return $lockedOrder->fresh(['table', 'items']);
            }

            if ($lockedOrder->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'order' => ['A cancelled order cannot be completed.'],
                ]);
            }

            if ($lockedOrder->itemCancellations()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'order' => ['Pending cancellation requests must be approved or rejected before this order can be completed.'],
                ]);
            }

            if (
                $tickets->isEmpty()
                || $tickets->contains(fn (KotTicket $ticket) => ! in_array(
                    strtolower($ticket->ticket_status),
                    ['ready', 'served', 'cancelled'],
                    true,
                ))
            ) {
                throw ValidationException::withMessages([
                    'order' => ['All active kitchen tickets must be ready or served before this order can be completed.'],
                ]);
            }

            $table = RestaurantTable::query()
                ->whereKey($lockedOrder->table_id)
                ->where('company_id', $lockedOrder->company_id)
                ->where('branch_id', $lockedOrder->branch_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOrder->update(['status' => 'completed']);

            foreach ($tickets as $ticket) {
                event(new KotTicketChanged(
                    (int) $ticket->id,
                    (int) $ticket->kitchen_id,
                    (int) $lockedOrder->id,
                    'updated',
                    (string) $ticket->ticket_status,
                ));
            }

            $anotherActiveOrder = Order::query()
                ->where('table_id', $table->id)
                ->where('id', '<>', $lockedOrder->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->exists();

            if (! $anotherActiveOrder) {
                $table->update(['table_status' => 'Available']);
            }

            return $lockedOrder->fresh(['table', 'items']);
        });
    }

    protected function syncOrderStatusFromTickets(Order $order): void
    {
        $statuses = $order->kotTickets()->pluck('ticket_status')->unique();
        $activeStatuses = $statuses
            ->map(fn ($status) => strtolower((string) $status))
            ->reject(fn ($status) => $status === 'cancelled');

        if ($activeStatuses->contains('preparing')) {
            $order->update(['status' => 'preparing']);
        } elseif ($activeStatuses->contains('new')) {
            $order->update(['status' => 'new']);
        } elseif ($activeStatuses->contains('accepted')) {
            $order->update(['status' => 'accepted']);
        } elseif ($activeStatuses->isNotEmpty() && $activeStatuses->every(fn ($status) => in_array(
            $status, ['ready', 'served'], true,
        ))) {
            $order->update(['status' => $activeStatuses->contains('ready') ? 'ready' : 'served']);
        }
    }

    protected function generateOrderNumber(int $branchId): string
    {
        $latestOrder = Order::withTrashed()
            ->where('order_number', 'like', 'ORD-%')
            ->where('order_number', 'not like', 'ORD-%-%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;
        if ($latestOrder && preg_match('/^ORD-(\d+)$/', $latestOrder->order_number, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        } else {
            $count = Order::withTrashed()
                ->where('order_number', 'like', 'ORD-%')
                ->where('order_number', 'not like', 'ORD-%-%')
                ->count();
            $nextNumber = max($nextNumber, $count + 1);
        }

        do {
            $candidate = sprintf('ORD-%04d', $nextNumber);
            $exists = Order::withTrashed()->where('order_number', $candidate)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidate;
    }

    protected function generateKotNumber(int $kitchenId): string
    {
        $prefix = sprintf('K%d-', $kitchenId);

        $latestTicket = KotTicket::withTrashed()
            ->where('kitchen_id', $kitchenId)
            ->where('kot_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;
        if ($latestTicket && preg_match('/^K\d+-(\d+)$/', $latestTicket->kot_number, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        } else {
            $count = KotTicket::withTrashed()
                ->where('kitchen_id', $kitchenId)
                ->where('kot_number', 'like', $prefix.'%')
                ->count();
            $nextNumber = max($nextNumber, $count + 1);
        }

        do {
            $candidate = sprintf('%s%04d', $prefix, $nextNumber);
            $exists = KotTicket::withTrashed()->where('kot_number', $candidate)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidate;
    }
}

