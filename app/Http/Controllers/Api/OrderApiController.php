<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Models\KotItem;
use App\Models\KotTicket;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderApiController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Submit a customer order.
     * POST /api/v1/orders
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'table_id' => ['required', 'exists:restaurant_tables,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        // If company_id or branch_id not in request, infer from authenticated waiter
        $validated['company_id'] = $validated['company_id'] ?? $user->company_id;
        $validated['branch_id'] = $validated['branch_id'] ?? $user->branch_id;

        $order = $this->orderService->createOrder($validated, $user);

        return response()->json([
            'message' => 'Order created and KOT tickets generated successfully.',
            'order' => $order,
        ], Response::HTTP_CREATED);
    }

    /**
     * List orders.
     * GET /api/v1/orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->with(['table', 'waiter', 'items.menuItem', 'kotTickets.kitchen']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        } elseif ($request->user()?->branch_id) {
            $query->where('branch_id', $request->user()->branch_id);
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', $request->table_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $orders = $query->latest()->paginate(20);

        return response()->json($orders);
    }

    /**
     * Retrieve order details.
     * GET /api/v1/orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'table',
            'waiter',
            'items.menuItem',
            'kotTickets.kitchen',
            'kotTickets.items.menuItem',
        ]);

        return response()->json($order);
    }

    /**
     * Add items to an existing order.
     * POST /api/v1/orders/{order}/items
     */
    public function addItems(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $updatedOrder = $this->orderService->addItemsToOrder($order, $validated['items'], $user);

        return response()->json([
            'message' => 'Additional items added and new KOT tickets generated.',
            'order' => $updatedOrder,
        ]);
    }

    /**
     * Retrieve KOT tickets for an order.
     * GET /api/v1/orders/{order}/kot
     */
    public function orderKot(Order $order): JsonResponse
    {
        $tickets = $order->kotTickets()->with(['kitchen', 'items.menuItem'])->get();

        return response()->json([
            'order_number' => $order->order_number,
            'table' => $order->table?->name,
            'tickets' => $tickets,
        ]);
    }

    /**
     * Retrieve KOT tickets for a kitchen display screen.
     * GET /api/v1/kitchens/{kitchen}/kot
     */
    public function kitchenKot(Kitchen $kitchen): JsonResponse
    {
        $tickets = KotTicket::where('kitchen_id', $kitchen->id)
            ->whereIn('ticket_status', ['new', 'accepted', 'preparing', 'ready'])
            ->with(['order.table', 'order.waiter', 'items.menuItem'])
            ->latest()
            ->get();

        return response()->json([
            'kitchen' => [
                'id' => $kitchen->id,
                'name' => $kitchen->name,
                'code' => $kitchen->code,
            ],
            'tickets' => $tickets,
        ]);
    }

    /**
     * Update KOT ticket status.
     * PATCH /api/v1/kot-tickets/{ticket}/status
     */
    public function updateTicketStatus(Request $request, KotTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,accepted,preparing,ready,served,cancelled'],
        ]);

        $updated = $this->orderService->updateKotTicketStatus($ticket, $validated['status']);

        return response()->json([
            'message' => 'KOT ticket status updated.',
            'ticket' => $updated,
        ]);
    }

    /**
     * Update individual KOT dish status.
     * PATCH /api/v1/kot-items/{item}/status
     */
    public function updateItemStatus(Request $request, KotItem $item): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,accepted,preparing,ready,served,cancelled'],
        ]);

        $updated = $this->orderService->updateKotItemStatus($item, $validated['status']);

        return response()->json([
            'message' => 'KOT item status updated.',
            'item' => $updated,
        ]);
    }
}

