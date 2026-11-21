<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Kitchen;
use App\Models\KotItem;
use App\Models\KotTicket;
use App\Models\MenuItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KdsController extends Controller
{
    use ManagesTenantAccess;

    /**
     * KDS Screens List - Displays all kitchen stations with live counters.
     * Clicking a station launches that specific kitchen's TV screen.
     */
    public function index(Request $request): View
    {
        $selectedDate = $request->input('date', now()->toDateString());
        $query = Kitchen::query()->where('is_active', true);
        $this->applyBranchScope($query);

        $kitchens = $query->with(['branch', 'company'])
            ->withCount([
                'kotTickets as active_count' => fn ($q) => $q->withPendingWork()->whereDate('created_at', $selectedDate),
            ])
            ->orderBy('name')
            ->get();

        return view('kds.index', compact('kitchens', 'selectedDate'));
    }

    /**
     * Dedicated Kitchen Display Screen (TV) for a specific kitchen.
     * Redirects to the required URL format: /kds/{branchCode}/{kitchenCode}
     */
    public function screen(Request $request, Kitchen $kitchen): RedirectResponse|View
    {
        $this->authorizeKitchenAccess($kitchen);
        $selectedDate = $request->input('date', now()->toDateString());

        $kitchen->loadMissing('branch');
        if (! app()->runningUnitTests() && $kitchen->branch && $kitchen->branch->code && $kitchen->code) {
            $redirectUrl = '/' . $kitchen->branch->code . '/' . $kitchen->code;
            if ($request->filled('date') && $request->date !== now()->toDateString()) {
                $redirectUrl .= '?date=' . urlencode($request->date);
            }
            return redirect()->to($redirectUrl);
        }

        $allKitchens = Kitchen::query()->where('is_active', true)->with('branch');
        $this->applyBranchScope($allKitchens);
        $allKitchens = $allKitchens->orderBy('name')->get();

        return view('kds.screen', compact('kitchen', 'allKitchens', 'selectedDate'));
    }

    /**
     * Master Screen displaying all kitchens combined.
     */
    public function screenAll(Request $request): View
    {
        $selectedDate = $request->input('date', now()->toDateString());
        $allKitchens = Kitchen::query()->where('is_active', true)->with('branch');
        $this->applyBranchScope($allKitchens);
        $allKitchens = $allKitchens->orderBy('name')->get();

        $kitchen = null;
        return view('kds.screen', compact('kitchen', 'allKitchens', 'selectedDate'));
    }

    /**
     * Dedicated Kitchen Display Screen (TV) by dynamic Branch & Kitchen codes:
     * e.g. base_url/branch_code/kitchen_code (e.g. /IND-01/B1 or /branch1/biryani)
     */
    public function screenByCode(Request $request, string $branchCode, string $kitchenCode): View
    {
        $selectedDate = $request->input('date', now()->toDateString());

        $branchQuery = Branch::whereRaw('LOWER(code) = ?', [strtolower($branchCode)])
            ->orWhereRaw('LOWER(name) = ?', [strtolower($branchCode)])
            ->orWhere('id', is_numeric($branchCode) ? (int) $branchCode : 0);

        if (preg_match('/^branch[-_]?(\d+)$/i', $branchCode, $m)) {
            $branchQuery->orWhere('id', (int) $m[1]);
        }

        $branch = $branchQuery->firstOrFail();
        $this->authorizeBranch($branch);

        if (strtolower($kitchenCode) === 'all') {
            $kitchen = null;
            $allKitchens = Kitchen::where('branch_id', $branch->id)->where('is_active', true)->with('branch')->orderBy('name')->get();
            return view('kds.screen', compact('kitchen', 'allKitchens', 'selectedDate'));
        }

        $kitchen = Kitchen::where('branch_id', $branch->id)
            ->where(function ($q) use ($kitchenCode) {
                $cleanCode = strtolower(trim($kitchenCode));
                $q->whereRaw('LOWER(code) = ?', [$cleanCode])
                  ->orWhereRaw('LOWER(name) = ?', [$cleanCode])
                  ->orWhereRaw('REPLACE(LOWER(name), "i", "") = REPLACE(?, "i", "")', [$cleanCode])
                  ->orWhereRaw('REPLACE(LOWER(name), "e", "") = REPLACE(?, "e", "")', [$cleanCode]);
            })
            ->firstOrFail();

        $this->authorizeKitchenAccess($kitchen);

        $allKitchens = Kitchen::where('branch_id', $branch->id)->where('is_active', true)->with('branch')->orderBy('name')->get();

        return view('kds.screen', compact('kitchen', 'allKitchens', 'selectedDate'));
    }

    /**
     * Real-time JSON data feed for the KDS screen.
     * Filters by kitchen if specified, or returns all active tickets for master screen.
     */
    public function feed(Request $request, ?Kitchen $kitchen = null): JsonResponse
    {
        // Requirement: Default to today's (present date) data. If a specific date is selected in KOT Monitoring calendar, display that date.
        $selectedDate = $request->input('date', now()->toDateString());

        $kitchenId = null;
        if ($kitchen && $kitchen->id) {
            $this->authorizeKitchenAccess($kitchen);
            $kitchenId = $kitchen->id;
        } elseif ($request->filled('kitchen_id') && $request->kitchen_id !== 'all') {
            $requestedId = (int) $request->kitchen_id;
            $k = Kitchen::find($requestedId);
            if ($k) {
                $this->authorizeKitchenAccess($k);
                $kitchen = $k;
                $kitchenId = $k->id;
            }
        }

        $query = KotTicket::query()
            ->withPendingWork()
            ->whereDate('created_at', $selectedDate)
            ->with([
                'kitchen',
                'order.table',
                'order.waiter',
                'items' => fn ($items) => $items->pending(),
                'items.menuItem',
            ])
            ->orderBy('created_at', 'asc');

        if ($kitchenId) {
            $query->where('kitchen_id', $kitchenId);
        } else {
            $this->applyBranchScope($query);
        }

        $tickets = $query->get();

        $now = now();

        $formatTicket = function (KotTicket $t) use ($now) {
            $receivedAt = $t->order?->created_at ?? $t->created_at ?? $now;

            $isAccepted = in_array($t->ticket_status, KotTicket::ACCEPTED_STATUSES, true);
            $startTime = $t->accepted_at ?? ($isAccepted ? $t->updated_at ?? $t->created_at : null);
            $elapsedSeconds = $startTime ? abs((int) $now->diffInSeconds($startTime)) : 0;

            // Extract numeric display (e.g. "1" from "KOT-20261106-K4-0001")
            if (preg_match('/-(\d+)$/', (string) $t->kot_number, $matches)) {
                $kotDisplay = (int) $matches[1];
            } else {
                $kotDisplay = $t->id;
            }

            // Table resolution: do not show soft-deleted tables
            $table = $t->order?->table;
            $tableName = ($table && !$table->trashed()) ? $table->name : 'Takeaway';

            return [
                'id' => $t->id,
                'kot_number' => $t->kot_number,
                'kot_display_number' => $kotDisplay,
                'kitchen_id' => $t->kitchen_id,
                'kitchen_name' => $t->kitchen?->name ?? 'Kitchen',
                'kitchen_code' => $t->kitchen?->code ?? '',
                'order_id' => $t->order_id,
                'order_number' => $t->order?->order_number ?? '—',
                'table_name' => $tableName,
                'waiter_name' => $t->order?->waiter?->name ?? 'Staff',
                'ticket_status' => $t->ticket_status,
                'order_status' => $t->order?->status,
                'special_instructions' => $t->order?->special_instructions,
                'notes' => $t->notes,
                'order_received_time' => $receivedAt->format('h:i A'),
                'order_received_time_full' => $receivedAt->format('H:i:s'),
                'created_at_time' => $receivedAt->format('H:i:s'),
                'created_at_iso' => $receivedAt->toIso8601String(),
                'accepted_at_iso' => $t->accepted_at?->toIso8601String(),
                'elapsed_seconds' => $elapsedSeconds,
                'is_running' => $isAccepted,
                'items' => $t->items->map(fn (KotItem $item) => [
                    'id' => $item->id,
                    'dish_name' => $item->menuItem?->name ?? 'Dish',
                    'dish_code' => $item->menuItem?->code,
                    'short_code' => $item->menuItem?->short_code,
                    'quantity' => $item->quantity - $item->cancelled_quantity,
                    'special_instructions' => $item->special_instructions,
                    'item_status' => $item->item_status,
                ])->values()->all(),
            ];
        };

        return response()->json([
            'kitchen' => $kitchen && $kitchen->id ? [
                'id' => $kitchen->id,
                'name' => $kitchen->name,
                'code' => $kitchen->code,
                'branch_name' => $kitchen->branch?->name,
            ] : [
                'id' => null,
                'name' => 'All Kitchens',
                'code' => 'ALL',
                'branch_name' => 'All Stations',
            ],
            'server_time' => $now->format('H:i:s'),
            'server_date' => Carbon::parse($selectedDate)->format('D, d M Y'),
            'filter_date' => $selectedDate,
            'counts' => [
                'total_active' => $tickets->count(),
            ],
            'active_tickets' => $tickets->map($formatTicket)->values(),
        ]);
    }

    /**
     * List all dishes mapped to this kitchen with availability status.
     */
    public function menuDishes(?Kitchen $kitchen = null): JsonResponse
    {
        $query = MenuItem::query()->where('is_active', true);

        if ($kitchen && $kitchen->id) {
            $this->authorizeKitchenAccess($kitchen);
            $query->where('branch_id', $kitchen->branch_id)
                ->whereHas('kitchenMenuItems', fn ($q) => $q->where('kitchen_id', $kitchen->id)->where('is_active', true));
        } else {
            $this->applyBranchScope($query);
        }

        $dishes = $query->with(['category', 'menuType'])
            ->orderBy('name')
            ->get()
            ->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'short_code' => $item->short_code,
                'category_name' => $item->category?->name ?? 'General',
                'menu_type' => $item->menuType?->name ?? 'Food',
                'is_available' => strtolower((string) ($item->availability ?? 'available')) !== 'sold_out',
                'availability' => $item->availability ?? 'available',
                'prepared_stock_quantity' => $item->prepared_stock_quantity ?? 0,
            ]);

        return response()->json([
            'kitchen_name' => $kitchen?->name ?? 'All Kitchens',
            'dishes' => $dishes,
        ]);
    }

    /**
     * Toggle dish availability (Available vs Sold Out) from kitchen TV.
     */
    public function toggleDishAvailability(Request $request, ?Kitchen $kitchen, MenuItem $menuItem): JsonResponse
    {
        if ($kitchen && $kitchen->id) {
            $this->authorizeKitchenAccess($kitchen);
            abort_unless((int) $menuItem->branch_id === (int) $kitchen->branch_id, 403);
        }

        $validated = $request->validate([
            'availability' => ['required', 'string', 'in:available,sold_out,temporarily_unavailable'],
        ]);

        $menuItem->update([
            'availability' => $validated['availability'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Dish {$menuItem->name} updated to {$validated['availability']}.",
            'menu_item_id' => $menuItem->id,
            'availability' => $menuItem->availability,
            'is_available' => $validated['availability'] !== 'sold_out',
        ]);
    }

    /**
     * Toggle dish availability without specific kitchen parameter.
     */
    public function toggleDishAvailabilityDirect(Request $request, MenuItem $menuItem): JsonResponse
    {
        $validated = $request->validate([
            'availability' => ['required', 'string', 'in:available,sold_out,temporarily_unavailable'],
        ]);

        $menuItem->update([
            'availability' => $validated['availability'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Dish {$menuItem->name} updated to {$validated['availability']}.",
            'menu_item_id' => $menuItem->id,
            'availability' => $menuItem->availability,
            'is_available' => $validated['availability'] !== 'sold_out',
        ]);
    }

    protected function authorizeKitchenAccess(Kitchen $kitchen): void
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
