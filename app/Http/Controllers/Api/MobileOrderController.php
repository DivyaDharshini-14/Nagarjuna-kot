<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ItemCancellation;
use App\Models\MobileApiIdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class MobileOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function show(Request $request, int $orderId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->company_id && $user->branch_id, 403, 'No branch is assigned to this account.');

        $order = Order::query()
            ->whereKey($orderId)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'order' => $this->orderPayload($order),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'min:1'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:100'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.menu_item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->company_id && $user->branch_id, 403, 'No branch is assigned to this account.');
        $idempotencyKey = $this->validatedIdempotencyKey($request);

        $orderData = [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            ...$validated,
        ];

        [$order, $replayed] = $this->runIdempotently(
            $user,
            $idempotencyKey,
            'create',
            $orderData,
            fn () => $this->orders->createOrder($orderData, $user),
        );

        return response()->json([
            'data' => [
                'order' => $this->orderPayload($order),
                'replayed' => $replayed,
            ],
        ], $replayed ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function addItems(Request $request, int $orderId): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'min:1'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.menu_item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->company_id && $user->branch_id, 403, 'No branch is assigned to this account.');

        $order = Order::query()
            ->whereKey($orderId)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('table_id', $validated['table_id'])
            ->firstOrFail();
        $idempotencyKey = $this->validatedIdempotencyKey($request);
        $operation = 'add:'.$order->id;

        [$updatedOrder, $replayed] = $this->runIdempotently(
            $user,
            $idempotencyKey,
            $operation,
            $validated,
            fn () => $this->orders->addItemsToOrder(
                $order,
                $validated['items'],
                $user,
                $validated['special_instructions'] ?? null,
            ),
        );

        return response()->json([
            'data' => [
                'order' => $this->orderPayload($updatedOrder),
                'replayed' => $replayed,
            ],
        ], Response::HTTP_OK);
    }

    public function updateItemInstructions(Request $request, int $orderId, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'special_instructions' => ['present', 'nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->company_id && $user->branch_id, 403, 'No branch is assigned to this account.');
        $order = Order::query()
            ->whereKey($orderId)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->firstOrFail();
        $item = OrderItem::query()
            ->where('order_id', $order->id)
            ->whereKey($itemId)
            ->firstOrFail();

        $updatedOrder = $this->orders->updateItemInstructions(
            $order,
            $item,
            $validated['special_instructions'],
            $user,
        );

        return response()->json([
            'message' => 'Dish instructions updated and sent to the kitchen.',
            'data' => ['order' => $this->orderPayload($updatedOrder)],
        ]);
    }

    public function complete(Request $request, int $orderId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->company_id && $user->branch_id, 403, 'No branch is assigned to this account.');

        $order = Order::query()
            ->whereKey($orderId)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->firstOrFail();

        $completedOrder = $this->orders->completeMobileOrder($order);

        return response()->json([
            'message' => 'Order completed and table released.',
            'data' => [
                'order' => $this->orderPayload($completedOrder),
            ],
        ]);
    }

    public function cancelItems(Request $request, int $orderId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('order.cancel'), Response::HTTP_FORBIDDEN, 'You are not allowed to request order cancellations.');
        abort_unless($user->company_id && $user->branch_id, Response::HTTP_FORBIDDEN, 'No branch is assigned to this account.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'cancel_all' => ['sometimes', 'boolean'],
            'items' => ['required_unless:cancel_all,true', 'array', 'min:1', 'max:100'],
            'items.*.order_item_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:99'],
        ]);

        $order = Order::query()
            ->whereKey($orderId)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->firstOrFail();

        [$batch, $cancellations] = $this->orders->requestItemCancellations(
            $order,
            $validated['items'] ?? [],
            trim($validated['reason']),
            (bool) ($validated['cancel_all'] ?? false),
            $user,
        );

        $freshOrder = $order->fresh();
        $approved = $cancellations->every(fn (ItemCancellation $cancellation) => $cancellation->status === 'approved');
        return response()->json([
            'message' => $approved
                ? 'Cancellation approved and applied.'
                : 'Cancellation request sent for manager approval.',
            'data' => [
                'request_batch' => $batch,
                'cancellations' => $cancellations->map(fn ($cancellation) => [
                    'order_item_id' => $cancellation->order_item_id,
                    'quantity' => $cancellation->quantity,
                    'status' => $cancellation->status,
                    'reason' => $cancellation->reason,
                ])->values(),
                'order' => $this->orderPayload($freshOrder),
            ],
        ], $approved ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
    }

    public function pendingCancellations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermission('order.approve'), Response::HTTP_FORBIDDEN, 'You are not allowed to approve cancellations.');
        abort_unless($user->company_id && $user->branch_id, Response::HTTP_FORBIDDEN, 'No branch is assigned to this account.');

        $approvalRequired = (bool) Branch::query()
            ->whereKey($user->branch_id)
            ->value('cancellation_approval_required');
        $requests = ItemCancellation::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', 'pending')
            ->with([
                'order:id,order_number,table_id,waiter_id',
                'order.table:id,name',
                'order.waiter:id,name',
                'orderItem:id,menu_item_id',
                'orderItem.menuItem:id,name',
                'requester:id,name',
            ])
            ->orderBy('created_at')
            ->get()
            ->groupBy('request_batch')
            ->map(function ($lines, $batch) use ($user, $approvalRequired) {
                $first = $lines->first();
                return [
                    'batch' => (string) $batch,
                    'order_id' => $first->order_id,
                    'order_number' => $first->order?->order_number ?? 'Order',
                    'table_name' => $first->order?->table?->name ?? '—',
                    'requested_by' => $first->requester?->name ?? 'Staff',
                    'can_approve' => ! $approvalRequired || (int) $first->requested_by !== (int) $user->id,
                    'requested_at' => $first->created_at?->toIso8601String(),
                    'items' => $lines->map(fn (ItemCancellation $line) => [
                        'name' => $line->orderItem?->menuItem?->name ?? 'Dish',
                        'quantity' => (int) $line->quantity,
                        'reason' => $line->reason,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json(['data' => ['requests' => $requests]]);
    }

    public function approveCancellation(Request $request, string $batch): JsonResponse
    {
        $user = $request->user();
        $this->assertPendingCancellationBatchScope($user, $batch);
        $this->orders->decideCancellationBatch($batch, $user, true);

        return response()->json(['message' => 'Cancellation approved and sent to the kitchen.']);
    }

    public function rejectCancellation(Request $request, string $batch): JsonResponse
    {
        $validated = $request->validate([
            'decision_note' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $user = $request->user();
        $this->assertPendingCancellationBatchScope($user, $batch);
        $this->orders->decideCancellationBatch($batch, $user, false, trim($validated['decision_note']));

        return response()->json(['message' => 'Cancellation request rejected.']);
    }

    private function assertPendingCancellationBatchScope(User $user, string $batch): void
    {
        abort_unless($user->hasPermission('order.approve'), Response::HTTP_FORBIDDEN, 'You are not allowed to decide cancellations.');
        abort_unless($user->company_id && $user->branch_id, Response::HTTP_FORBIDDEN, 'No branch is assigned to this account.');
        abort_unless(ItemCancellation::query()
            ->where('request_batch', $batch)
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', 'pending')
            ->exists(), 404);
    }

    private function validatedIdempotencyKey(Request $request): string
    {
        $validated = Validator::make(
            ['idempotency_key' => $request->header('Idempotency-Key')],
            ['idempotency_key' => ['required', 'uuid']],
        )->validate();

        return $validated['idempotency_key'];
    }

    /**
     * @return array{0: Order, 1: bool}
     */
    private function runIdempotently(
        User $user,
        string $key,
        string $operation,
        array $payload,
        Closure $perform,
    ): array {
        $requestHash = hash('sha256', json_encode([
            'operation' => $operation,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));

        $existing = MobileApiIdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $key)
            ->first();
        if ($existing) {
            return $this->replayOrReject($existing, $operation, $requestHash);
        }

        try {
            $order = DB::transaction(function () use ($user, $key, $operation, $requestHash, $perform) {
                $entry = MobileApiIdempotencyKey::create([
                    'user_id' => $user->id,
                    'idempotency_key' => $key,
                    'operation' => $operation,
                    'request_hash' => $requestHash,
                ]);

                /** @var Order $order */
                $order = $perform();
                $entry->update(['order_id' => $order->id]);

                return $order;
            });

            return [$order, false];
        } catch (QueryException $exception) {
            $existing = MobileApiIdempotencyKey::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $key)
                ->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->replayOrReject($existing, $operation, $requestHash);
        }
    }

    /**
     * @return array{0: Order, 1: true}
     */
    private function replayOrReject(MobileApiIdempotencyKey $entry, string $operation, string $requestHash): array
    {
        abort_unless(
            $entry->operation === $operation && hash_equals($entry->request_hash, $requestHash),
            Response::HTTP_CONFLICT,
            'This idempotency key was already used for a different request.',
        );
        abort_unless($entry->order_id, Response::HTTP_CONFLICT, 'This request is still being processed. Retry shortly.');

        $order = Order::query()->findOrFail($entry->order_id);

        return [$order, true];
    }

    private function orderPayload(Order $order): array
    {
        $order->loadMissing([
            'table:id,name',
            'items.menuItem:id,name',
            'items.kotItems:id,order_item_id,item_status',
            'items.cancellations:id,order_item_id,quantity,status,reason,requested_by,decided_by,decided_at,decision_note,stock_reversed,stock_reversed_quantity,wastage_quantity,created_at',
            'items.cancellations.requester:id,name',
            'items.cancellations.decider:id,name',
            'kotTickets.kitchen:id,name',
            'kotTickets.items.menuItem:id,name',
        ]);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'updated_at' => $order->updated_at?->toIso8601String(),
            'guest_count' => $order->guest_count,
            'table' => [
                'id' => $order->table?->id,
                'name' => $order->table?->name,
            ],
            'total_amount' => (string) $order->total_amount,
            'special_instructions' => $order->special_instructions,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'menu_item_id' => $item->menu_item_id,
                'name' => $item->menuItem?->name,
                'quantity' => $item->quantity,
                'cancelled_quantity' => $item->cancelled_quantity,
                'pending_cancellation_quantity' => $item->cancellations->where('status', 'pending')->sum('quantity'),
                'cancellable_quantity' => max(0, $item->quantity - $item->cancelled_quantity - $item->cancellations->where('status', 'pending')->sum('quantity')),
                'can_modify' => ! in_array($order->status, ['completed', 'cancelled'], true)
                    && $item->cancelled_quantity < $item->quantity
                    && $item->cancellations->where('status', 'pending')->isEmpty()
                    && $item->kotItems->isNotEmpty()
                    && $item->kotItems->every(fn ($kotItem) => in_array(strtolower((string) $kotItem->item_status), ['new', 'accepted'], true)),
                'unit_price' => (string) $item->unit_price,
                'total_price' => (string) $item->total_price,
                'special_instructions' => $item->special_instructions,
                'status' => $item->status,
                'cancellations' => $item->cancellations->map(fn ($cancellation) => [
                    'quantity' => $cancellation->quantity,
                    'status' => $cancellation->status,
                    'reason' => $cancellation->reason,
                    'decision_note' => $cancellation->decision_note,
                    'stock_reversed_quantity' => $cancellation->stock_reversed_quantity,
                    'wastage_quantity' => $cancellation->wastage_quantity,
                    'requested_at' => $cancellation->created_at?->toIso8601String(),
                    'decided_at' => $cancellation->decided_at?->toIso8601String(),
                    'requested_by' => $cancellation->requester?->name,
                    'decided_by' => $cancellation->decider?->name,
                ])->values(),
            ])->values(),
            'kot_tickets' => $order->kotTickets->map(fn ($ticket) => [
                'id' => $ticket->id,
                'kot_number' => $ticket->kot_number,
                'kitchen_name' => $ticket->kitchen?->name,
                'status' => $ticket->ticket_status,
                'items' => $ticket->items->map(fn ($item) => [
                    'menu_item_id' => $item->menu_item_id,
                    'name' => $item->menuItem?->name,
                    'quantity' => $item->quantity,
                    'status' => $item->item_status,
                ])->values(),
            ])->values(),
        ];
    }
}
