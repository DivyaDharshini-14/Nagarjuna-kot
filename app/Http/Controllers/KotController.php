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
        $kitchens = $branchId
            ? Kitchen::where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get()
            : Kitchen::where('is_active', true)->orderBy('name')->get();

        return view('kots.index', compact('kots', 'kitchens'));
    }

    public function updateStatus(Request $request, KotTicket $kot): RedirectResponse
    {
        $this->authorizeKotAccess($kot);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,accepted,preparing,ready,served,cancelled'],
        ]);

        $this->orderService->updateKotTicketStatus($kot, $validated['status']);

        return back()->with('success', "KOT #{$kot->kot_number} status updated to {$validated['status']}.");
    }

    public function display(Kitchen $kitchen): View
    {
        $this->authorizeKitchenDisplay($kitchen);

        $tickets = KotTicket::where('kitchen_id', $kitchen->id)
            ->whereIn('ticket_status', ['new', 'accepted', 'preparing', 'ready'])
            ->with(['order.table', 'order.waiter', 'items.menuItem'])
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
            ->get();

        return view('kots.display', compact('kitchen', 'tickets'));
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

