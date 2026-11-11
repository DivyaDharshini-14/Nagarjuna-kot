<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Kitchen;
use App\Models\KotTicket;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KotController extends Controller
{
    use ManagesTenantAccess;

    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request): View
    {
        $kots = KotTicket::query()
            ->with(['kitchen', 'order.table', 'order.waiter', 'items.menuItem']);

        $this->applyBranchScope($kots);

        if ($request->filled('kitchen_id')) {
            $kots->where('kitchen_id', $request->kitchen_id);
        }

        if ($request->filled('status')) {
            $kots->where('ticket_status', $request->status);
        }

        if ($request->filled('date')) {
            $kots->whereDate('created_at', $request->date);
        }

        $kots = $kots->latest()->paginate(12)->withQueryString();

        $branchId = $this->locksBranch() ? $this->actor()->branch_id : ($request->branch_id ?? null);
        $kitchens = $branchId
            ? Kitchen::where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get()
            : Kitchen::where('is_active', true)->orderBy('name')->get();

        return view('kots.index', compact('kots', 'kitchens'));
    }

    public function updateStatus(Request $request, KotTicket $kot): RedirectResponse
    {
        $this->authorizeKotAccess($kot);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,accepted,preparing,ready,served'],
        ]);

        $this->orderService->updateKotTicketStatus($kot, $validated['status']);

        return back()->with('success', "KOT #{$kot->kot_number} status updated to {$validated['status']}.");
    }

    public function display(Kitchen $kitchen): View
    {
        $this->authorizeKitchenDisplay($kitchen);

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
        $allowedTransitions = [
            'new' => ['accepted'],
            'accepted' => ['preparing'],
            'preparing' => ['ready'],
            'ready' => ['served'],
        ];
        abort_unless(
            $kot->ticket_status === $validated['status']
                || in_array($validated['status'], $allowedTransitions[$kot->ticket_status] ?? [], true),
            409,
        );
        $this->orderService->updateKotTicketStatus($kot, $validated['status']);

        return redirect()->route('kds.registered-display', $displayToken);
    }

    private function renderDisplay(Kitchen $kitchen, ?string $displayToken = null): View
    {
        $tickets = KotTicket::where('kitchen_id', $kitchen->id)
            ->where(function ($query) {
                $query->whereIn('ticket_status', ['new', 'accepted', 'preparing', 'ready'])
                    ->orWhere(fn ($cancelled) => $cancelled
                        ->where('ticket_status', 'cancelled')
                        ->where('updated_at', '>=', now()->subMinutes(15)));
            })
            ->with(['order.table', 'order.waiter', 'items.menuItem', 'items.orderItem.cancellations'])
            ->orderBy('created_at')
            ->get();

        return view('kots.display', compact('kitchen', 'tickets', 'displayToken'));
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

    protected function authorizeKotAccess(KotTicket $kot): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        abort_unless((int) $kot->company_id === (int) $this->actor()->company_id, 403);

        if ($this->locksBranch()) {
            abort_unless((int) $kot->branch_id === (int) $this->actor()->branch_id, 403);
        }
    }

    protected function authorizeKitchenDisplay(Kitchen $kitchen): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        abort_unless((int) $kitchen->company_id === (int) $this->actor()->company_id, 403);

        if ($this->locksBranch()) {
            abort_unless((int) $kitchen->branch_id === (int) $this->actor()->branch_id, 403);
        }
    }
}

