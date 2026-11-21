<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = DB::table('order_items')->select('id')
            ->whereColumn('order_items.order_id', 'orders.id')
            ->whereColumn('cancelled_quantity', '<', 'quantity')
            ->whereNull('deleted_at');

        $unfinished = (clone $items)->whereNotIn('status', ['ready', 'served', 'cancelled']);
        $finished = (clone $items)->whereIn('status', ['ready', 'served']);
        $ready = (clone $items)->where('status', 'ready');

        $orders = DB::table('orders')->whereNull('deleted_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereExists($finished);

        (clone $orders)->whereExists($unfinished)->update(['status' => 'partially_ready']);
        (clone $orders)->whereNotExists($unfinished)->whereExists($ready)->update(['status' => 'ready']);
        $orders->whereNotExists($unfinished)->whereNotExists($ready)->update(['status' => 'served']);

        // Older tickets could remain open when all unfinished dishes were cancelled.
        $dishes = DB::table('kot_items')->select('id')
            ->whereColumn('kot_items.kot_ticket_id', 'kot_tickets.id')
            ->whereColumn('cancelled_quantity', '<', 'quantity');
        $readyDishes = (clone $dishes)->where('item_status', 'ready');
        $unfinishedDishes = (clone $dishes)->whereNotIn('item_status', ['ready', 'served', 'cancelled']);
        $tickets = DB::table('kot_tickets')->whereNull('deleted_at')
            ->whereIn('ticket_status', ['new', 'accepted', 'preparing'])
            ->whereNotExists($unfinishedDishes);

        (clone $tickets)->whereExists($readyDishes)->update(['ticket_status' => 'ready']);
        $tickets->whereNotExists($readyDishes)->whereExists($dishes->where('item_status', 'served'))
            ->update(['ticket_status' => 'served']);
    }

    public function down(): void
    {
        // Current item readiness continues to describe each order after a rollback.
    }
};
