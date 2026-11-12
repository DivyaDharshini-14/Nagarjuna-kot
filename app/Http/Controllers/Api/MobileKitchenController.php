<?php

namespace App\Http\Controllers\Api;

use App\Events\MenuAvailabilityChanged;
use App\Http\Controllers\Controller;
use App\Models\Kitchen;
use App\Models\KotTicket;
use App\Models\MenuItem;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileKitchenController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (
            ! $user
            || ! Hash::check($validated['password'], $user->password)
            || ! $this->canUseKitchenApp($user)
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect or unavailable.'],
            ]);
        }

        $expiresAt = now()->addDays(7);
        $token = $user->createToken($validated['device_name'], ['kitchen:mobile'], $expiresAt);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => ['user' => $this->userPayload($request->user())]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function kitchens(Request $request): JsonResponse
    {
        $user = $request->user();
        $kitchens = $this->assignedKitchens($user)
            ->map(fn (Kitchen $kitchen) => $this->kitchenPayload($kitchen));

        return response()->json([
            'data' => [
                'branch' => [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                ],
                'kitchens' => $kitchens,
            ],
        ]);
    }

    public function allTickets(Request $request): JsonResponse
    {
        $user = $request->user();
        $kitchens = $this->assignedKitchens($user);
        $view = $this->requestedTicketView($request);
        $tickets = $this->ticketsForKitchens($user, $kitchens->modelKeys(), $view);

        return response()->json([
            'data' => [
                'kitchens' => $kitchens->map(fn (Kitchen $kitchen) => $this->kitchenPayload($kitchen))->values(),
                'tickets' => $tickets,
                'view' => $view,
                'refreshed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function tickets(Request $request, Kitchen $kitchen): JsonResponse
    {
        $this->authorizeKitchen($request->user(), $kitchen);
        $view = $this->requestedTicketView($request);
        $tickets = $this->ticketsForKitchens($request->user(), [$kitchen->id], $view);

        return response()->json([
            'data' => [
                'kitchen' => [
                    'id' => $kitchen->id,
                    'name' => $kitchen->name,
                    'code' => $kitchen->code,
                ],
                'tickets' => $tickets,
                'view' => $view,
                'refreshed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function assignedKitchens(User $user)
    {
        return $user->kitchens()
            ->where('kitchens.company_id', $user->company_id)
            ->where('kitchens.branch_id', $user->branch_id)
            ->where('kitchens.is_active', true)
            ->orderBy('kitchens.name')
            ->get(['kitchens.id', 'kitchens.company_id', 'kitchens.branch_id', 'kitchens.name', 'kitchens.code']);
    }

    private function kitchenPayload(Kitchen $kitchen): array
    {
        return [
            'id' => $kitchen->id,
            'name' => $kitchen->name,
            'code' => $kitchen->code,
        ];
    }

    private function requestedTicketView(Request $request): string
    {
        $view = $request->query('view', 'active');
        abort_unless(in_array($view, ['active', 'completed', 'cancelled'], true), 422, 'The kitchen board view is invalid.');

        return $view;
    }

    private function ticketsForKitchens(User $user, array $kitchenIds, string $view)
    {
        $query = KotTicket::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->whereIn('kitchen_id', $kitchenIds)
            ->with([
                'order:id,order_number,table_id,created_at,special_instructions,status',
                'order.table:id,name',
                'items:id,kot_ticket_id,menu_item_id,quantity,cancelled_quantity,special_instructions,item_status',
                'items.menuItem:id,name,menu_type_id',
                'items.menuItem.menuType:id,name',
            ]);

        if ($view === 'active') {
            $query->whereHas('order', fn ($order) => $order->where('status', '<>', 'completed'))
                ->where(function ($tickets) {
                $tickets->whereIn('ticket_status', ['new', 'accepted', 'preparing', 'ready'])
                    ->orWhere(fn ($cancelled) => $cancelled
                        ->where('ticket_status', 'cancelled')
                        ->where('updated_at', '>=', now()->subMinutes(15)));
                })
                ->orderBy('created_at');
        } elseif ($view === 'completed') {
            $query->where(fn ($tickets) => $tickets
                ->where('ticket_status', 'served')
                ->orWhereHas('order', fn ($order) => $order->where('status', 'completed')))
                ->orderByDesc('updated_at')
                ->limit(100);
        } else {
            $query->where(fn ($tickets) => $tickets
                ->where('ticket_status', 'cancelled')
                ->orWhereHas('items', fn ($items) => $items->where('cancelled_quantity', '>', 0)))
                ->orderByDesc('updated_at')
                ->limit(100);
        }

        return $query->get();
    }

    public function updateTicketStatus(Request $request, KotTicket $ticket): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            (int) $ticket->company_id === (int) $user->company_id
                && (int) $ticket->branch_id === (int) $user->branch_id,
            404
        );

        $kitchen = Kitchen::query()->findOrFail($ticket->kitchen_id);
        $this->authorizeKitchen($user, $kitchen);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:accepted,preparing,ready'],
        ]);

        $current = $ticket->ticket_status;
        $next = $validated['status'];
        $allowedTransitions = [
            'new' => ['accepted'],
            'accepted' => ['preparing'],
            'preparing' => ['ready'],
        ];

        if ($current !== $next && ! in_array($next, $allowedTransitions[$current] ?? [], true)) {
            return response()->json([
                'message' => 'This ticket has changed. Refresh the kitchen board before updating it.',
            ], 409);
        }

        $updated = $current === $next
            ? $ticket->fresh(['order.table', 'items.menuItem.menuType'])
            : $this->orderService->updateKotTicketStatus($ticket, $next);

        return response()->json([
            'message' => 'KOT ticket updated.',
            'data' => ['ticket' => $updated],
        ]);
    }

    public function stock(Request $request): JsonResponse
    {
        $user = $request->user();
        $kitchenIds = $this->assignedKitchens($user)->modelKeys();
        $items = MenuItem::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->whereHas('kitchenMenuItems', fn ($query) => $query
                ->whereIn('kitchen_id', $kitchenIds)
                ->where('is_active', true))
            ->with(['kitchenMenuItems' => fn ($query) => $query
                ->whereIn('kitchen_id', $kitchenIds)
                ->where('is_active', true)
                ->with('kitchen:id,name')])
            ->orderBy('name')
            ->get()
            ->map(fn (MenuItem $item) => $this->stockPayload($item));

        return response()->json(['data' => ['items' => $items, 'can_manage_stock' => $user->hasPermission('menu-item.stock-adjustment')]]);
    }

    public function adjustStock(Request $request, MenuItem $menuItem): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('menu-item.stock-adjustment'), 403);
        $this->authorizeMenuItem($user, $menuItem);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:stock_in,wastage,damaged,manual_adjustment'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'direction' => ['required_if:type,manual_adjustment', 'nullable', 'in:increase,decrease'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $updated = \Illuminate\Support\Facades\DB::transaction(function () use ($menuItem, $user, $validated) {
            $locked = MenuItem::query()->whereKey($menuItem->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->stock_tracking_enabled, 422, 'Prepared stock tracking must be enabled in menu management first.');
            $wasConfigured = $locked->prepared_stock_quantity !== null;
            $before = (int) ($locked->prepared_stock_quantity ?? 0);
            $increases = $validated['type'] === 'stock_in'
                || ($validated['type'] === 'manual_adjustment' && $validated['direction'] === 'increase');
            $delta = $increases ? (int) $validated['quantity'] : -(int) $validated['quantity'];
            $after = $before + $delta;
            abort_if($after < 0, 422, 'Stock cannot be reduced below zero.');

            $locked->prepared_stock_quantity = $after;
            $locked->save();
            StockTransaction::create([
                'company_id' => $locked->company_id,
                'branch_id' => $locked->branch_id,
                'menu_item_id' => $locked->id,
                'user_id' => $user->id,
                'transaction_type' => ! $wasConfigured && $increases ? 'opening_stock' : $validated['type'],
                'quantity' => (int) $validated['quantity'],
                'quantity_delta' => $delta,
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => trim($validated['reason']),
            ]);

            return $locked->fresh(['kitchenMenuItems.kitchen:id,name']);
        });
        event(new MenuAvailabilityChanged(
            (int) $updated->branch_id,
            (int) $updated->id,
            (string) $updated->availability,
            $updated->prepared_stock_quantity === null ? null : (int) $updated->prepared_stock_quantity,
        ));

        return response()->json(['message' => 'Prepared stock updated.', 'data' => ['item' => $this->stockPayload($updated)]]);
    }

    public function stockTransactions(Request $request, MenuItem $menuItem): JsonResponse
    {
        $user = $request->user();
        $this->authorizeMenuItem($user, $menuItem);
        $transactions = StockTransaction::query()
            ->where('company_id', $user->company_id)
            ->where('branch_id', $user->branch_id)
            ->where('menu_item_id', $menuItem->id)
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (StockTransaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->transaction_type,
                'quantity' => $transaction->quantity,
                'quantity_delta' => $transaction->quantity_delta,
                'stock_before' => $transaction->stock_before,
                'stock_after' => $transaction->stock_after,
                'reason' => $transaction->reason,
                'user' => $transaction->user?->name ?? 'Former staff account',
                'created_at' => $transaction->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => ['transactions' => $transactions]]);
    }

    public function setAvailability(Request $request, MenuItem $menuItem): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('menu-item.stock-adjustment'), 403);
        $this->authorizeMenuItem($user, $menuItem);
        $validated = $request->validate([
            'availability' => ['required', 'string', 'in:available,temporarily_unavailable,sold_out'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $updated = DB::transaction(function () use ($menuItem, $user, $validated) {
            $locked = MenuItem::query()->whereKey($menuItem->id)->lockForUpdate()->firstOrFail();
            if ($locked->availability === $validated['availability']) return $locked;
            $stock = (int) ($locked->prepared_stock_quantity ?? 0);
            StockTransaction::create([
                'company_id' => $locked->company_id,
                'branch_id' => $locked->branch_id,
                'menu_item_id' => $locked->id,
                'user_id' => $user->id,
                'transaction_type' => 'availability_change',
                'quantity' => 0,
                'quantity_delta' => 0,
                'stock_before' => $stock,
                'stock_after' => $stock,
                'reason' => trim($validated['reason']).' ('.$locked->availability.' → '.$validated['availability'].')',
            ]);
            $locked->update(['availability' => $validated['availability']]);
            return $locked;
        });
        event(new MenuAvailabilityChanged(
            (int) $updated->branch_id,
            (int) $updated->id,
            (string) $updated->availability,
            $updated->prepared_stock_quantity === null ? null : (int) $updated->prepared_stock_quantity,
        ));

        return response()->json(['message' => 'Dish availability updated.', 'data' => ['item' => $this->stockPayload($updated->fresh('kitchenMenuItems.kitchen:id,name'))]]);
    }

    private function authorizeMenuItem(User $user, MenuItem $menuItem): void
    {
        $assignedKitchenIds = $this->assignedKitchens($user)->modelKeys();
        abort_unless(
            (int) $menuItem->company_id === (int) $user->company_id
                && (int) $menuItem->branch_id === (int) $user->branch_id
                && $menuItem->is_active
                && $menuItem->kitchenMenuItems()
                    ->whereIn('kitchen_id', $assignedKitchenIds)
                    ->where('is_active', true)
                    ->exists(),
            404,
        );
    }

    private function stockPayload(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'stock_tracking_enabled' => (bool) $item->stock_tracking_enabled,
            'prepared_stock_quantity' => $item->prepared_stock_quantity,
            'availability' => $item->availability,
            'kitchens' => $item->kitchenMenuItems->map(fn ($mapping) => [
                'id' => $mapping->kitchen?->id,
                'name' => $mapping->kitchen?->name,
            ])->values(),
        ];
    }

    private function authorizeKitchen(User $user, Kitchen $kitchen): void
    {
        abort_unless(
            $kitchen->is_active
                && (int) $kitchen->company_id === (int) $user->company_id
                && (int) $kitchen->branch_id === (int) $user->branch_id
                && $user->kitchens()->whereKey($kitchen->id)->exists(),
            404
        );
    }

    private function canUseKitchenApp(User $user): bool
    {
        return $user->canAuthenticate()
            && $user->company_id !== null
            && $user->branch_id !== null
            && $user->isKitchenStaff()
            && $user->hasPermission('kot.view')
            && $user->hasPermission('kot.edit');
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing(['company:id,name,code', 'branch:id,company_id,name,code']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company' => [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'code' => $user->company->code,
            ],
            'branch' => [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
                'code' => $user->branch->code,
            ],
            'can_manage_stock' => $user->hasPermission('menu-item.stock-adjustment'),
        ];
    }
}
