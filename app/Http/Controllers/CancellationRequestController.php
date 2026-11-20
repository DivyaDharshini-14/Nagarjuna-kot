<?php

namespace App\Http\Controllers;

use App\Models\ItemCancellation;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CancellationRequestController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): View
    {
        // Requirement: Default to today's (present date) data. If a specific date is selected in the calendar, filter by that date.
        $selectedDate = $request->filled('date') ? $request->date : now()->toDateString();

        $query = ItemCancellation::query()
            ->with(['order.table', 'order.waiter', 'orderItem.menuItem', 'requester', 'decider']);

        $user = $request->user();
        if ($user?->company_id) {
            $query->where('company_id', $user->company_id);
        }
        if ($user?->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        if ($selectedDate && $selectedDate !== 'all') {
            $query->whereDate('created_at', $selectedDate);
        }

        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->where(function ($q) use ($term) {
                $q->whereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%"))
                    ->orWhereHas('orderItem.menuItem', fn ($m) => $m->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('requester', fn ($r) => $r->where('name', 'like', "%{$term}%"))
                    ->orWhere('reason', 'like', "%{$term}%");
            });
        }

        $totalCancelledQuantity = (clone $query)->sum('quantity');
        $cancellations = $query->latest('created_at')->latest('id')->paginate(15)->withQueryString();

        return view('cancellations.index', compact('cancellations', 'totalCancelledQuantity', 'selectedDate'));
    }

    public function approve(Request $request, string $batch): RedirectResponse
    {
        $this->orders->decideCancellationBatch($batch, $request->user(), true);
        return back()->with('success', 'Cancellation request approved and applied.');
    }

    public function reject(Request $request, string $batch): RedirectResponse
    {
        $validated = $request->validate(['decision_note' => ['required', 'string', 'min:3', 'max:500']]);
        $this->orders->decideCancellationBatch($batch, $request->user(), false, trim($validated['decision_note']));
        return back()->with('success', 'Cancellation request rejected.');
    }
}
