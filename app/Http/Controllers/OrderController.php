<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    use ManagesTenantAccess;

    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with(['company', 'branch', 'table.floor', 'waiter', 'kotTickets.kitchen', 'items.menuItem']);

        $this->applyBranchScope($orders);

        if ($request->filled('status')) {
            $orders->where('status', $request->status);
        }

        if ($request->filled('table_id')) {
            $orders->where('table_id', $request->table_id);
        }

        if ($request->filled('date')) {
            $orders->whereDate('created_at', $request->date);
        }

        if ($request->filled('search')) {
            $term = trim($request->search);
            $orders->where(function ($q) use ($term) {
                $q->where('order_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%")
                    ->orWhereHas('table', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('waiter', fn ($w) => $w->where('name', 'like', "%{$term}%"));
            });
        }

        $orders = $orders->latest()->paginate(10)->withQueryString();

        $state = $this->formState();
        $branchId = $state['selectedBranch'] ? (int) $state['selectedBranch'] : $this->actor()->branch_id;

        $tables = $branchId ? RestaurantTable::where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get() : collect();

        return view('orders.index', compact('orders', 'tables'));
    }

    public function create(Request $request): View
    {
        $state = $this->formState();
        $branchId = $state['selectedBranch'] ? (int) $state['selectedBranch'] : $this->actor()->branch_id;
        $companyId = $state['selectedCompany'] ? (int) $state['selectedCompany'] : $this->actor()->company_id;

        $tables = $branchId
            ? RestaurantTable::where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get()
            : collect();

        $categories = $companyId
            ? Category::where('company_id', $companyId)
                ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
                ->where('is_active', true)
                ->orderBy('display_order')
                ->with(['menuItems' => function ($q) use ($branchId) {
                    $q->where('is_active', true)
                        ->where(fn ($sub) => $sub->whereNull('branch_id')->orWhere('branch_id', $branchId))
                        ->orderBy('display_order');
                }])
                ->get()
            : collect();

        $selectedTableId = $request->query('table_id');

        return view('orders.create', array_merge($state, compact('tables', 'categories', 'selectedTableId')));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mergeLockedTenant($request);

        $validated = $request->validate([
            'company_id' => [$this->locksCompany() ? 'required' : 'nullable', 'exists:companies,id'],
            'branch_id' => [$this->locksBranch() ? 'required' : 'nullable', 'exists:branches,id'],
            'table_id' => ['required', 'exists:restaurant_tables,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:255'],
        ]);

        $table = RestaurantTable::findOrFail($validated['table_id']);
        $validated['company_id'] = $validated['company_id'] ?? $table->company_id;
        $validated['branch_id'] = $validated['branch_id'] ?? $table->branch_id;

        $order = $this->orderService->createOrder($validated, $this->actor());

        return redirect()->route('orders.show', $order)->with('success', "Order #{$order->order_number} submitted and KOT tickets generated successfully.");
    }

    public function show(Order $order): View
    {
        $this->authorizeOrderAccess($order);

        $order->load([
            'company',
            'branch',
            'table.floor',
            'waiter',
            'items.menuItem.category',
            'kotTickets.kitchen',
            'kotTickets.items.menuItem',
        ]);

        $categories = Category::where('company_id', $order->company_id)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $order->branch_id))
            ->where('is_active', true)
            ->with(['menuItems' => function ($q) use ($order) {
                $q->where('is_active', true)
                    ->where(fn ($sub) => $sub->whereNull('branch_id')->orWhere('branch_id', $order->branch_id))
                    ->orderBy('name');
            }])
            ->get();

        return view('orders.show', compact('order', 'categories'));
    }

    public function addItems(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrderAccess($order);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:255'],
        ]);

        $this->orderService->addItemsToOrder($order, $validated['items'], $this->actor());

        return redirect()->route('orders.show', $order)->with('success', 'Additional items added and new KOT tickets generated.');
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrderAccess($order);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,preparing,ready,served,completed'],
        ]);

        $this->orderService->updateOrderStatus($order, $validated['status']);

        return back()->with('success', "Order status updated to {$validated['status']}.");
    }

    protected function authorizeOrderAccess(Order $order): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        abort_unless((int) $order->company_id === (int) $this->actor()->company_id, 403);

        if ($this->locksBranch()) {
            abort_unless((int) $order->branch_id === (int) $this->actor()->branch_id, 403);
        }
    }
}

