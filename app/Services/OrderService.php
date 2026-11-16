<?php

namespace App\Services;

use App\Events\KotTicketChanged;
use App\Events\MenuAvailabilityChanged;
use App\Models\Branch;
use App\Models\ItemCancellation;
use App\Models\Kitchen;
use App\Models\KitchenMenuItem;
use App\Models\KotItem;
use App\Models\KotTicket;
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
        }, 3);
    }

    /**
     * Add additional items to an existing order and generate KOT tickets only for the new items.
     */
    public function addItemsToOrder(
        Order $order,
        array $items,
        User $waiter,
        ?string $orderInstructions = null
    ): Order {
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

            if ((! $waiter->isAdmin() && (int) $waiter->company_id !== (int) $order->company_id)
                || (! $waiter->isAdmin() && ! $waiter->hasRole('company-admin') && (int) $waiter->branch_id !== (int) $order->branch_id)) {
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
        }, 3);
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
            if ($kotItems->isEmpty() || $kotItems->contains(fn (KotItem $item) => ! in_array(strtolower((string) $item->item_status), ['new', 'accepted'], true)
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
        }, 3);
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
            $mappings = KitchenMenuItem::query()
                ->where('company_id', $order->company_id)
                ->where('branch_id', $order->branch_id)
                ->where('menu_item_id', $item->menu_item_id)
                ->where('is_active', true)
                ->whereHas('kitchen', fn ($query) => $query
                    ->where('company_id', $order->company_id)
                    ->where('branch_id', $order->branch_id)
                    ->where('is_active', true))
                ->orderBy('kitchen_id')
                ->get();

            if ($mappings->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['A selected dish has no active assigned kitchen. Ask a manager to configure its kitchen mapping.'],
                ]);
            }

            // Every station receives the full preparation task; customer stock
            // and cancellations are accounted for once on the order line.
            foreach ($mappings->pluck('kitchen_id')->unique() as $kitchenId) {
                $itemsByKitchen[$kitchenId][] = $item;
            }
        }

        $tickets = collect();
        $numbers = app(DocumentNumbers::class);
        $period = $numbers->kotPeriod((int) $order->branch_id);

        foreach ($itemsByKitchen as $kitchenId => $items) {
            $kotNumber = $numbers->kot((int) $order->branch_id, $period);

            $ticket = KotTicket::create([
                'kot_number' => $kotNumber,
                'numbering_period' => $period,
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
            $order = $this->lockOrderAndTickets((int) $ticket->order_id);
            abort_if(in_array($order->status, ['completed', 'cancelled'], true), 409, 'This order is closed.');
            $locked = KotTicket::query()->whereKey($ticket->id)->firstOrFail();
            $this->syncTicketStatus($locked);
            $this->assertPreparationTransition((string) $locked->ticket_status, $status);
            if ($locked->ticket_status === $status) {
                return $locked->fresh(['kitchen', 'items.menuItem', 'order']);
            }

            foreach ($locked->items()->orderBy('id')->lockForUpdate()->get() as $line) {
                if ($line->cancelled_quantity >= $line->quantity) {
                    $this->syncOrderItemStatus((int) $line->order_item_id);

                    continue;
                }
                // Mixed per-item work may already be further ahead. Never move
                // those portions backwards when advancing the whole ticket.
                if ($this->statusRank((string) $line->item_status) < $this->statusRank($status)) {
                    $line->update(['item_status' => $status]);
                }
                $this->syncOrderItemStatus((int) $line->order_item_id);
            }
            $this->syncTicketStatus($locked);
            $this->syncOrderStatusFromTickets($order);
            event(new KotTicketChanged(
                (int) $locked->id,
                (int) $locked->kitchen_id,
                (int) $order->id,
                'updated',
                (string) $locked->ticket_status,
            ));

            return $locked->fresh(['kitchen', 'items.menuItem', 'order']);
        }, 3);
    }

    /**
     * Update status of an individual KOT item.
     */
    public function updateKotItemStatus(KotItem $item, string $status): KotItem
    {
        return DB::transaction(function () use ($item, $status) {
            $ticket = $item->ticket()->firstOrFail();
            $order = $this->lockOrderAndTickets((int) $ticket->order_id);
            abort_if(in_array($order->status, ['completed', 'cancelled'], true), 409, 'This order is closed.');
            $lockedItem = KotItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedItem->cancelled_quantity >= $lockedItem->quantity, 409, 'This dish is cancelled.');
            $this->assertPreparationTransition((string) $lockedItem->item_status, $status);
            if ($lockedItem->item_status === $status) {
                return $lockedItem->fresh(['ticket', 'orderItem', 'menuItem']);
            }
            $lockedItem->update(['item_status' => $status]);
            $this->syncOrderItemStatus((int) $lockedItem->order_item_id);
            $ticket->refresh();
            $this->syncTicketStatus($ticket);
            $this->syncOrderStatusFromTickets($order);
            event(new KotTicketChanged(
                (int) $ticket->id,
                (int) $ticket->kitchen_id,
                (int) $ticket->order_id,
                'updated',
                (string) $ticket->ticket_status,
            ));

            return $lockedItem->fresh(['ticket', 'orderItem', 'menuItem']);
        }, 3);
    }

    private function lockOrderAndTickets(int $orderId): Order
    {
        $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
        KotTicket::query()->where('order_id', $orderId)->orderBy('id')->lockForUpdate()->get(['id']);

        return $order;
    }

    private function assertPreparationTransition(string $current, string $next): void
    {
        $allowed = ['new' => 'accepted', 'accepted' => 'preparing', 'preparing' => 'ready', 'ready' => 'served'];
        abort_unless((isset($allowed[$current]) && ($current === $next || $allowed[$current] === $next))
            || ($current === 'served' && $next === 'served'), 409,
            'This ticket has changed. Refresh before continuing. Cancellations require the reasoned cancellation workflow.');
    }

    private function statusRank(string $status): int
    {
        return ['new' => 0, 'accepted' => 1, 'preparing' => 2, 'ready' => 3, 'served' => 4][$status] ?? 0;
    }

    private function slowestStatus(Collection $statuses): string
    {
        return (string) ($statuses->sortBy(fn ($status) => $this->statusRank((string) $status))->first() ?? 'cancelled');
    }

    private function syncOrderItemStatus(int $itemId): void
    {
        $line = OrderItem::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
        $status = $line->cancelled_quantity >= $line->quantity ? 'cancelled' : $this->slowestStatus(
            KotItem::query()->where('order_item_id', $itemId)->whereColumn('cancelled_quantity', '<', 'quantity')->pluck('item_status'),
        );
        if ($line->status !== $status) {
            $line->update(['status' => $status]);
        }
    }

    private function syncTicketStatus(KotTicket $ticket): void
    {
        foreach ($ticket->items()->whereColumn('cancelled_quantity', '>=', 'quantity')->where('item_status', '<>', 'cancelled')->get() as $line) {
            $line->update(['item_status' => 'cancelled']);
            $this->syncOrderItemStatus((int) $line->order_item_id);
        }
        $status = $this->slowestStatus($ticket->items()->whereColumn('cancelled_quantity', '<', 'quantity')->pluck('item_status'));
        $data = ['ticket_status' => $status];
        $now = now();
        if (in_array($status, ['accepted', 'preparing', 'ready', 'served'], true) && ! $ticket->accepted_at) {
            $data['accepted_at'] = $now;
        }
        if (in_array($status, ['preparing', 'ready', 'served'], true) && ! $ticket->preparing_at) {
            $data['preparing_at'] = $now;
        }
        if (in_array($status, ['ready', 'served'], true) && ! $ticket->ready_at) {
            $data['ready_at'] = $now;
        }
        if (in_array($status, ['ready', 'served'], true) && $ticket->duration_seconds === null) {
            // Dispatch must preserve the preparation time frozen at ready, including zero.
            $startTime = $ticket->accepted_at ?? $data['accepted_at'] ?? $ticket->created_at ?? $now;
            $endTime = $ticket->ready_at ?? $data['ready_at'] ?? $now;
            $data['duration_seconds'] = max(0, (int) $startTime->diffInSeconds($endTime, false));
        }
        $ticket->fill($data);
        if ($ticket->isDirty()) {
            $ticket->save();
        }
    }

    /** Mark a fully ready order line served across every kitchen ticket that contains it. */
    public function markMobileOrderItemServed(Order $order, OrderItem $item): Order
    {
        return DB::transaction(function () use ($order, $item) {
            $lockedOrder = $this->lockOrderAndTickets((int) $order->id);
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
                $kotItems->every(fn (KotItem $kotItem) => in_array(strtolower((string) $kotItem->item_status), ['ready', 'served'], true)),
                409,
                'Every kitchen handling this dish must mark it ready before it can be served.',
            );

            $kotItems->where('item_status', 'ready')->each(fn (KotItem $kotItem) => $kotItem->update(['item_status' => 'served']));
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
                $this->syncTicketStatus($ticket);
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
        }, 3);
    }

    /**
     * Update order status directly (e.g. completed, cancelled).
     */
    public function updateOrderStatus(Order $order, string $status): Order
    {
        abort_unless($status === 'completed', 409, 'Preparation status comes from the kitchen. Use the reasoned cancellation workflow for cancellations.');

        return $this->completeMobileOrder($order);
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
            $lockedOrder = $this->lockOrderAndTickets((int) $order->id);
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
                    if ($available > 0) {
                        $requestedQuantities[$item->id] = $available;
                    }
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

            return [$batch, $created->map(fn (ItemCancellation $record) => $record->fresh(['orderItem.menuItem', 'requester'])
            )];
        }, 3);
    }

    /** Approve or reject all pending line cancellations in one request batch. */
    public function decideCancellationBatch(string $batch, User $actor, bool $approve, ?string $note = null): void
    {
        abort_unless($actor->hasPermission('order.approve'), 403);
        DB::transaction(function () use ($batch, $actor, $approve, $note) {
            $first = ItemCancellation::query()->where('request_batch', $batch)->firstOrFail();
            $this->lockOrderAndTickets((int) $first->order_id);
            $records = ItemCancellation::query()
                ->where('request_batch', $batch)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            abort_if($records->isEmpty(), 404);
            abort_unless($records->every(fn (ItemCancellation $record) => ($actor->isAdmin() || (int) $record->company_id === (int) $actor->company_id)
                && ($actor->isAdmin() || $actor->hasRole('company-admin') || (int) $record->branch_id === (int) $actor->branch_id)
            ), 404);
            abort_unless($records->every(fn (ItemCancellation $record) => $record->status === 'pending'), 409);

            $approvalRequired = (bool) Branch::query()
                ->whereKey($records->first()->branch_id)
                ->value('cancellation_approval_required');
            if ($approve && $approvalRequired) {
                abort_unless($records->every(fn (ItemCancellation $record) => (int) $record->requested_by !== (int) $actor->id
                ), 403, 'A cancellation requester cannot approve their own request for this branch.');
            }

            if (! $approve) {
                foreach ($records as $record) {
                    $record->update([
                        'status' => 'rejected',
                        'decided_by' => $actor->id,
                        'decided_at' => now(),
                        'decision_note' => $note,
                        'updated_at' => now(),
                    ]);
                }

                return;
            }

            foreach ($records as $record) {
                $record->update([
                    'status' => 'approved',
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                    'decision_note' => $note,
                    'updated_at' => now(),
                ]);
            }
            $this->applyCancellationBatch($batch, $actor);
        }, 3);
    }

    private function applyCancellationBatch(string $batch, User $actor): void
    {
        $records = ItemCancellation::query()
            ->where('request_batch', $batch)
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();
        $firstRecord = $records->firstOrFail();
        $order = $this->lockOrderAndTickets((int) $firstRecord->order_id);
        abort_if(in_array($order->status, ['completed', 'cancelled'], true), 409);

        $changedTickets = [];
        $menuIds = OrderItem::query()->whereIn('id', $records->pluck('order_item_id'))->pluck('menu_item_id');
        MenuItem::query()->whereIn('id', $menuIds)->orderBy('id')->lockForUpdate()->get(['id']);
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
            // Each mapped station carries the same customer quantity. Cancel
            // that quantity at EVERY station, but restore stock only once and
            // only if no station has begun preparing any of these portions.
            if ($kotItems->isEmpty()) {
                throw ValidationException::withMessages(['items' => ['This order line has no kitchen work to cancel.']]);
            }
            $canRestore = $kotItems->every(fn (KotItem $line) => in_array(strtolower((string) $line->item_status), ['new', 'accepted'], true));
            $stockReversedQuantity = $canRestore ? (int) $record->quantity : 0;
            $wastageQuantity = $canRestore ? 0 : (int) $record->quantity;
            foreach ($kotItems as $kotItem) {
                $available = $kotItem->quantity - $kotItem->cancelled_quantity;
                if ($available < $record->quantity) {
                    throw ValidationException::withMessages(['items' => ['A kitchen quantity changed before cancellation. Refresh the order.']]);
                }
                $kotItem->cancelled_quantity += $record->quantity;
                if ($kotItem->cancelled_quantity >= $kotItem->quantity) {
                    $kotItem->item_status = 'cancelled';
                }
                $kotItem->save();
                $changedTickets[$kotItem->kot_ticket_id] = true;
            }

            $item->cancelled_quantity += $record->quantity;
            if ($item->cancelled_quantity >= $item->quantity) {
                $item->status = 'cancelled';
            }
            $amountCents = (int) round((int) $record->quantity * (float) $item->unit_price * 100);
            $remainingLineCents = max(0, (int) round((float) $item->total_price * 100) - $amountCents);
            $item->total_price = number_format($remainingLineCents / 100, 2, '.', '');
            $item->save();
            $this->syncOrderItemStatus((int) $item->id);
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
            if (! $ticket) {
                continue;
            }
            $this->syncTicketStatus($ticket);
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
                if (! $anotherActiveOrder) {
                    $table->update(['table_status' => 'Available']);
                }
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
            $lockedOrder = $this->lockOrderAndTickets((int) $order->id);
            $tickets = $lockedOrder->kotTickets()->get(['id', 'kitchen_id', 'ticket_status']);

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
        }, 3);
    }

    protected function syncOrderStatusFromTickets(Order $order): void
    {
        if (in_array($order->status, ['completed', 'cancelled'], true)) {
            return;
        }
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
        return app(DocumentNumbers::class)->order($branchId);
    }
}
