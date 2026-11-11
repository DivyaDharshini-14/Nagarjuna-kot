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
        $query = ItemCancellation::query()
            ->with(['order.table', 'order.waiter', 'orderItem.menuItem', 'requester'])
            ->where('status', 'pending');
        $user = $request->user();
        if ($user->company_id) $query->where('company_id', $user->company_id);
        if ($user->branch_id) $query->where('branch_id', $user->branch_id);

        $requests = $query->latest()->get()->groupBy('request_batch');
        return view('cancellations.index', compact('requests'));
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
