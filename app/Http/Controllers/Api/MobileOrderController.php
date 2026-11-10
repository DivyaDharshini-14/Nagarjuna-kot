<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileApiIdempotencyKey;
use App\Models\Order;
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
                'unit_price' => (string) $item->unit_price,
                'total_price' => (string) $item->total_price,
                'special_instructions' => $item->special_instructions,
                'status' => $item->status,
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
