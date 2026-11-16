<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Http\Controllers\Concerns\ScopesKitchenOperations;
use App\Models\Kitchen;
use App\Models\KotTicket;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KotController extends Controller
{
    use ManagesTenantAccess, ScopesKitchenOperations;

    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request): View
    {
        $kots = KotTicket::query()
            ->with(['kitchen', 'order.table', 'order.waiter', 'items.menuItem']);

        $this->scopeKitchenTickets($kots);

        if ($request->filled('kitchen_id')) {
            $kots->where('kitchen_id', $request->kitchen_id);
        }

        if ($request->filled('status')) {
            $kots->where('ticket_status', $request->status);
        }

        if ($request->filled('date')) {
            $kots->whereDate('created_at', $request->date);
        }

        $kots = $kots
            ->orderByRaw("
                CASE ticket_status
                    WHEN 'new' THEN 1
                    WHEN 'accepted' THEN 2
                    WHEN 'preparing' THEN 3
                    WHEN 'ready' THEN 4
                    WHEN 'served' THEN 5
                    WHEN 'cancelled' THEN 6
                    ELSE 7
                END ASC
            ")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $branchId = $this->locksBranch() ? $this->actor()->branch_id : ($request->branch_id ?? null);
        $kitchens = $this->accessibleKitchens()->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')->get();

        return view('kots.index', compact('kots', 'kitchens'));
    }

    public function updateStatus(Request $request, KotTicket $kot): RedirectResponse
    {
        $this->authorizeKotAccess($kot);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:accepted,preparing,ready,served'],
        ]);

        $this->orderService->updateKotTicketStatus($kot, $validated['status']);

        return back()->with('success', "KOT #{$kot->kot_number} status updated to {$validated['status']}.");
    }

    public function display(Kitchen $kitchen): View
    {
        $this->authorizeKitchenAccess($kitchen);

        return $this->renderDisplay($kitchen);
    }

    public function registeredDisplay(string $displayToken): View
    {
        return $this->renderDisplay($this->registeredKitchen($displayToken), $displayToken);
    }

    public function registeredDisplayBroadcastAuth(Request $request, string $displayToken): JsonResponse
    {
        $kitchen = $this->registeredKitchen($displayToken);
        $validated = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/', 'max:100'],
            'channel_name' => ['required', 'string', 'max:150'],
        ]);
        $channelName = 'private-kitchens.'.$kitchen->id;
        abort_unless(hash_equals($channelName, $validated['channel_name']), 403);

        $key = (string) config('broadcasting.connections.reverb.key');
        $secret = (string) config('broadcasting.connections.reverb.secret');
        abort_if($key === '' || $secret === '', 503, 'Kitchen live updates are not configured.');
        $signature = hash_hmac('sha256', $validated['socket_id'].':'.$channelName, $secret);

        return response()->json(['auth' => $key.':'.$signature]);
    }

    public function registeredDisplayUpdateStatus(Request $request, string $displayToken, KotTicket $kot): RedirectResponse
    {
        $kitchen = $this->registeredKitchen($displayToken);
        abort_unless((int) $kot->kitchen_id === (int) $kitchen->id, 404);
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:accepted,preparing,ready,served'],
        ]);
        $this->orderService->updateKotTicketStatus($kot, $validated['status']);

        return redirect()->route('kds.registered-display', $displayToken);
    }

    private function renderDisplay(Kitchen $kitchen, ?string $displayToken = null): View
    {
        request()->validate(['history' => ['nullable', 'in:cancelled,completed'], 'history_page' => ['nullable', 'integer', 'min:1']]);
        $query = KotTicket::where('kitchen_id', $kitchen->id)
            ->where('company_id', $kitchen->company_id)->where('branch_id', $kitchen->branch_id)
            ->with(['order.table', 'order.waiter', 'items.menuItem', 'items.orderItem.cancellations']);
        $tickets = (clone $query)->whereIn('ticket_status', ['new', 'accepted', 'preparing', 'ready'])
            ->whereHas('order', fn ($order) => $order->whereNotIn('status', ['completed', 'cancelled']))
            ->oldest()->get();
        $historyType = request('history', 'cancelled');
        if ($historyType === 'completed') {
            $query->where(fn ($history) => $history->where('ticket_status', 'served')
                ->orWhereHas('order', fn ($order) => $order->where('status', 'completed')));
        } else {
            $query->where(fn ($history) => $history->where('ticket_status', 'cancelled')
                ->orWhereHas('items', fn ($items) => $items->where('cancelled_quantity', '>', 0)));
        }
        $historyTickets = $query->latest('updated_at')->latest('id')->paginate(24, ['*'], 'history_page')->withQueryString();

        return view('kots.display', compact('kitchen', 'tickets', 'displayToken', 'historyTickets', 'historyType'));
    }

    private function registeredKitchen(string $displayToken): Kitchen
    {
        return Kitchen::query()
            ->where('display_token_hash', hash('sha256', $displayToken))
            ->where('is_active', true)
            ->whereHas('company', fn ($query) => $query->where('is_active', true))
            ->whereHas('branch', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();
    }
}
