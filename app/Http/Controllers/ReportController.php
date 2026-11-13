<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Kitchen;
use App\Models\KotItem;
use App\Models\KotTicket;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ManagesTenantAccess;

    /**
     * Display the Order Report with analytical filters, KPI summary, and export options.
     */
    public function orders(Request $request): View
    {
        $query = $this->buildFilteredOrderQuery($request);

        // Summary metrics across all matching records (before pagination)
        $summaryQuery = clone $query;
        $totalOrders = $summaryQuery->count();
        $totalRevenue = (clone $query)->whereNotIn('status', ['cancelled'])->sum('total_amount');
        $completedCount = (clone $query)->whereIn('status', ['served', 'completed'])->count();
        $inProgressCount = (clone $query)->whereIn('status', ['new', 'accepted', 'preparing', 'ready', 'confirmed', 'pending'])->count();
        $cancelledCount = (clone $query)->where('status', 'cancelled')->count();

        // Paginated results for the report table
        $orders = $query
            ->with([
                'company:id,name,code',
                'branch:id,name,code,company_id',
                'table.floor:id,name',
                'waiter:id,name,email',
                'items.menuItem:id,name,code,category_id',
                'kotTickets.kitchen:id,name,code',
            ])
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        // Filter dropdown options according to tenant RBAC
        $options = $this->resolveFilterOptions($request);

        return view('reports.orders', array_merge($options, [
            'orders' => $orders,
            'metrics' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'completed' => $completedCount,
                'in_progress' => $inProgressCount,
                'cancelled' => $cancelledCount,
            ],
            'filters' => [
                'date_preset' => $request->input('date_preset', 'all_time'),
                'date' => $request->input('date'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'company_id' => $request->input('company_id'),
                'branch_id' => $request->input('branch_id'),
                'kitchen_id' => $request->input('kitchen_id'),
                'status' => $request->input('status'),
                'table_id' => $request->input('table_id'),
                'waiter_id' => $request->input('waiter_id'),
                'search' => $request->input('search'),
            ],
        ]));
    }

    /**
     * Export the filtered Order Report dataset to a clean CSV spreadsheet.
     */
    public function exportOrders(Request $request): StreamedResponse
    {
        $query = $this->buildFilteredOrderQuery($request)
            ->with([
                'company:id,name',
                'branch:id,name',
                'table.floor:id,name',
                'waiter:id,name',
                'items.menuItem:id,name',
                'kotTickets.kitchen:id,name',
            ])
            ->orderBy('created_at', 'asc');

        $filename = 'order-report-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Header Row
            fputcsv($handle, [
                'Sl No',
                'Order Number',
                'Date',
                'Time',
                'Company',
                'Branch',
                'Table',
                'Floor',
                'Waiter',
                'Customer Name',
                'Kitchen Stations',
                'Dishes (Items)',
                'Total Items',
                'Total Amount (INR)',
                'Status',
            ]);

            $slNo = 1;
            $query->chunk(200, function ($orders) use ($handle, &$slNo) {
                foreach ($orders as $order) {
                    $kitchens = $order->kotTickets
                        ->map(fn ($k) => $k->kitchen?->name)
                        ->filter()
                        ->unique()
                        ->join(', ');

                    $itemsList = $order->items
                        ->map(fn ($it) => ($it->menuItem?->name ?? 'Dish') . ' x' . $it->quantity)
                        ->join('; ');

                    fputcsv($handle, [
                        $slNo++,
                        $order->order_number,
                        $order->created_at->format('Y-m-d'),
                        $order->created_at->format('h:i A'),
                        $order->company?->name ?? '—',
                        $order->branch?->name ?? '—',
                        $order->table?->name ?? '—',
                        $order->table?->floor?->name ?? '—',
                        $order->waiter?->name ?? '—',
                        $order->customer_name ?? '—',
                        $kitchens ?: '—',
                        $itemsList ?: '—',
                        $order->items->sum('quantity'),
                        number_format((float) $order->total_amount, 2, '.', ''),
                        ucfirst($order->status),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Display the KOT Report with analytical filters, KPI summary, and export options.
     */
    public function kots(Request $request): View
    {
        $query = $this->buildFilteredKotQuery($request);

        // Summary metrics across all matching records (before pagination)
        $summaryQuery = clone $query;
        $totalKots = $summaryQuery->count();
        $servedCount = (clone $query)->whereIn('ticket_status', ['served', 'completed'])->count();
        $readyCount = (clone $query)->where('ticket_status', 'ready')->count();
        $inProgressCount = (clone $query)->whereIn('ticket_status', ['new', 'accepted', 'preparing'])->count();
        $cancelledCount = (clone $query)->where('ticket_status', 'cancelled')->count();

        // Paginated results for the report table
        $kots = $query
            ->with([
                'company:id,name,code',
                'branch:id,name,code,company_id',
                'kitchen:id,name,code',
                'order:id,order_number,table_id,waiter_id,status,created_at',
                'order.table:id,name,floor_id',
                'order.table.floor:id,name',
                'order.waiter:id,name',
                'items.menuItem:id,name,code',
            ])
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $options = $this->resolveFilterOptions($request);

        return view('reports.kots', array_merge($options, [
            'kots' => $kots,
            'metrics' => [
                'total_kots' => $totalKots,
                'served' => $servedCount,
                'ready' => $readyCount,
                'in_progress' => $inProgressCount,
                'cancelled' => $cancelledCount,
            ],
            'filters' => [
                'date_preset' => $request->input('date_preset', 'all_time'),
                'date' => $request->input('date'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'company_id' => $request->input('company_id'),
                'branch_id' => $request->input('branch_id'),
                'kitchen_id' => $request->input('kitchen_id'),
                'status' => $request->input('status'),
                'table_id' => $request->input('table_id'),
                'waiter_id' => $request->input('waiter_id'),
                'search' => $request->input('search'),
            ],
        ]));
    }

    /**
     * Export the filtered KOT Report dataset to a clean CSV spreadsheet.
     */
    public function exportKots(Request $request): StreamedResponse
    {
        $query = $this->buildFilteredKotQuery($request)
            ->with([
                'company:id,name',
                'branch:id,name',
                'kitchen:id,name',
                'order:id,order_number,table_id,waiter_id',
                'order.table:id,name,floor_id',
                'order.table.floor:id,name',
                'order.waiter:id,name',
                'items.menuItem:id,name',
            ])
            ->orderBy('created_at', 'asc');

        $filename = 'kot-report-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Header Row
            fputcsv($handle, [
                'Sl No',
                'KOT Number',
                'Order Number',
                'Kitchen Station',
                'Branch',
                'Table',
                'Floor',
                'Waiter',
                'Items Summary',
                'Total Items Qty',
                'Ticket Status',
                'Created Date',
                'Created Time',
                'Accepted At',
                'Preparing At',
                'Ready At',
                'Duration (Seconds)',
            ]);

            $slNo = 1;
            $query->chunk(200, function ($kots) use ($handle, &$slNo) {
                foreach ($kots as $kot) {
                    $itemsSummary = $kot->items
                        ->map(fn ($it) => ($it->menuItem?->name ?? 'Dish') . ' x' . $it->quantity)
                        ->join('; ');

                    $totalQty = $kot->items->sum('quantity');

                    fputcsv($handle, [
                        $slNo++,
                        $kot->kot_number,
                        $kot->order?->order_number ?? '—',
                        $kot->kitchen?->name ?? '—',
                        $kot->branch?->name ?? '—',
                        $kot->order?->table?->name ?? '—',
                        $kot->order?->table?->floor?->name ?? '—',
                        $kot->order?->waiter?->name ?? '—',
                        $itemsSummary ?: '—',
                        $totalQty,
                        ucfirst($kot->ticket_status),
                        $kot->created_at->format('Y-m-d'),
                        $kot->created_at->format('h:i A'),
                        $kot->accepted_at?->format('Y-m-d h:i A') ?? '—',
                        $kot->preparing_at?->format('Y-m-d h:i A') ?? '—',
                        $kot->ready_at?->format('Y-m-d h:i A') ?? '—',
                        $kot->duration_seconds ?? '—',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Display the Menu Items Report with dish-level quantity, revenue analytics, and KPI summary.
     */
    public function menuItems(Request $request): View
    {
        $query = $this->buildFilteredMenuItemQuery($request);

        // Calculate KPI summary metrics across all matching menu items
        $allItems = (clone $query)->get();
        $totalItemsCount = $allItems->count();
        $totalQuantitySold = (int) $allItems->sum('total_quantity_ordered');
        $totalRevenue = (float) $allItems->sum('total_revenue');
        $totalCancelled = (int) $allItems->sum('total_cancelled_quantity');
        $topSeller = $allItems->sortByDesc('total_quantity_ordered')->first(fn ($it) => ($it->total_quantity_ordered ?? 0) > 0);

        // Paginate results
        $menuItems = $query->paginate(20)->withQueryString();

        $options = $this->resolveFilterOptions($request);

        // Categories and Menu Types for dropdowns
        $companyId = $options['selectedCompanyId'];
        $categoriesQuery = Category::where('is_active', true);
        if ($companyId) {
            $categoriesQuery->where('company_id', $companyId);
        }
        $categories = $categoriesQuery->orderBy('name')->get(['id', 'name']);

        $menuTypes = MenuType::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('reports.menu-items', array_merge($options, [
            'menuItems' => $menuItems,
            'categories' => $categories,
            'menuTypes' => $menuTypes,
            'metrics' => [
                'total_items' => $totalItemsCount,
                'total_quantity_sold' => $totalQuantitySold,
                'total_revenue' => $totalRevenue,
                'total_cancelled' => $totalCancelled,
                'top_seller' => $topSeller ? ($topSeller->name . ' (' . ($topSeller->total_quantity_ordered ?? 0) . ' sold)') : '—',
            ],
            'filters' => [
                'date_preset' => $request->input('date_preset', 'all_time'),
                'date' => $request->input('date'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'company_id' => $request->input('company_id'),
                'branch_id' => $request->input('branch_id'),
                'category_id' => $request->input('category_id'),
                'menu_type_id' => $request->input('menu_type_id'),
                'kitchen_id' => $request->input('kitchen_id'),
                'is_active' => $request->input('is_active'),
                'search' => $request->input('search'),
            ],
        ]));
    }

    /**
     * Export the filtered Menu Items Report dataset to a clean CSV spreadsheet.
     */
    public function exportMenuItems(Request $request): StreamedResponse
    {
        $query = $this->buildFilteredMenuItemQuery($request);
        $filename = 'menu-items-report-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Header Row
            fputcsv($handle, [
                'Sl No',
                'Dish Code',
                'Dish Name',
                'Category',
                'Menu Type',
                'Assigned Kitchen(s)',
                'Branch',
                'Selling Price (INR)',
                'Total Orders',
                'Qty Ordered',
                'Cancelled Qty',
                'Net Sold Qty',
                'Total Revenue (INR)',
                'Status',
            ]);

            $slNo = 1;
            $query->chunk(200, function ($items) use ($handle, &$slNo) {
                foreach ($items as $item) {
                    $kitchens = $item->kitchenMenuItems
                        ->map(fn ($k) => $k->kitchen?->name)
                        ->filter()
                        ->unique()
                        ->join(', ');

                    $ordered = (int) ($item->total_quantity_ordered ?? 0);
                    $cancelled = (int) ($item->total_cancelled_quantity ?? 0);
                    $netSold = max(0, $ordered - $cancelled);
                    $revenue = (float) ($item->total_revenue ?? 0.00);

                    fputcsv($handle, [
                        $slNo++,
                        $item->code ?? '—',
                        $item->name,
                        $item->category?->name ?? '—',
                        $item->menuType?->name ?? '—',
                        $kitchens ?: '—',
                        $item->branch?->name ?? '—',
                        number_format((float) $item->selling_price, 2, '.', ''),
                        (int) ($item->orders_count ?? 0),
                        $ordered,
                        $cancelled,
                        $netSold,
                        number_format($revenue, 2, '.', ''),
                        $item->is_active ? 'Active' : 'Inactive',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Build filtered query for MenuItem with aggregated order item counts, quantities and revenues.
     */
    private function buildFilteredMenuItemQuery(Request $request): Builder
    {
        $user = $this->actor();
        $isSuper = $this->isSuperAdmin();
        $locksBranch = $this->locksBranch();

        // Subquery callback to filter order items based on tenant and date preset
        $orderFilterCallback = function (Builder $q) use ($request, $user, $isSuper, $locksBranch) {
            $q->whereNotIn('status', ['cancelled']);

            if ($isSuper && $request->filled('company_id')) {
                $q->where('company_id', (int) $request->company_id);
            } elseif (! $isSuper && $user->company_id) {
                $q->where('company_id', $user->company_id);
            }

            if ($locksBranch) {
                $q->where('branch_id', $user->branch_id);
            } elseif ($request->filled('branch_id')) {
                $q->where('branch_id', (int) $request->branch_id);
            }

            $preset = $request->input('date_preset', 'all_time');
            $date = $request->input('date');
            $from = $request->input('date_from');
            $to = $request->input('date_to');

            switch ($preset) {
                case 'today':
                    $q->whereDate('created_at', Carbon::today());
                    break;
                case 'yesterday':
                    $q->whereDate('created_at', Carbon::yesterday());
                    break;
                case 'this_week':
                    $q->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $q->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                    break;
                case 'last_30_days':
                    $q->whereDate('created_at', '>=', Carbon::now()->subDays(30));
                    break;
                case 'single_date':
                    if ($date) {
                        $q->whereDate('created_at', Carbon::parse($date));
                    }
                    break;
                case 'custom':
                    if ($from && $to) {
                        $q->whereBetween('created_at', [
                            Carbon::parse($from)->startOfDay(),
                            Carbon::parse($to)->endOfDay(),
                        ]);
                    } elseif ($from) {
                        $q->whereDate('created_at', '>=', Carbon::parse($from));
                    } elseif ($to) {
                        $q->whereDate('created_at', '<=', Carbon::parse($to));
                    }
                    break;
                case 'all_time':
                default:
                    break;
            }
        };

        $query = MenuItem::query()
            ->with([
                'category:id,name',
                'menuType:id,name',
                'kitchenMenuItems.kitchen:id,name',
                'branch:id,name',
                'company:id,name',
            ])
            ->withCount([
                'orderItems as orders_count' => fn ($q) => $q->whereHas('order', $orderFilterCallback),
            ])
            ->withSum([
                'orderItems as total_quantity_ordered' => fn ($q) => $q->whereHas('order', $orderFilterCallback),
            ], 'quantity')
            ->withSum([
                'orderItems as total_cancelled_quantity' => fn ($q) => $q->whereHas('order', $orderFilterCallback),
            ], 'cancelled_quantity')
            ->withSum([
                'orderItems as total_revenue' => fn ($q) => $q->whereHas('order', $orderFilterCallback),
            ], 'total_price');

        // Multi-tenant scoping on MenuItem
        $this->applyBranchScope($query);

        if ($isSuper && $request->filled('company_id')) {
            $query->where('company_id', (int) $request->company_id);
        }

        if ($locksBranch) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }

        // Menu Type filter
        if ($request->filled('menu_type_id')) {
            $query->where('menu_type_id', (int) $request->menu_type_id);
        }

        // Kitchen filter
        if ($request->filled('kitchen_id')) {
            $kId = (int) $request->kitchen_id;
            $query->whereHas('kitchenMenuItems', fn ($k) => $k->where('kitchen_id', $kId));
        }

        // Search text
        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        // Active status
        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === '1');
        }

        // Ordering: primary by total quantity ordered desc, then name asc
        $query->orderByDesc('total_quantity_ordered')->orderBy('name');

        return $query;
    }

    /**
     * Core query builder that applies all filter parameters and multi-tenant scoping for KOT tickets.
     */
    private function buildFilteredKotQuery(Request $request): Builder
    {
        $query = KotTicket::query();
        $this->applyBranchScope($query);

        $user = $this->actor();
        $isSuper = $this->isSuperAdmin();
        $locksBranch = $this->locksBranch();

        // 1. Company Filter
        if ($isSuper && $request->filled('company_id')) {
            $query->where('company_id', (int) $request->company_id);
        } elseif (! $isSuper && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        // 2. Branch Filter
        if ($locksBranch) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        // 3. Kitchen Filter
        if ($request->filled('kitchen_id')) {
            $query->where('kitchen_id', (int) $request->kitchen_id);
        }

        // 4. Ticket Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('ticket_status', $request->status);
        }

        // 5. Table Filter (via order)
        if ($request->filled('table_id')) {
            $tableId = (int) $request->table_id;
            $query->whereHas('order', fn ($o) => $o->where('table_id', $tableId));
        }

        // 6. Waiter Filter (via order)
        if ($request->filled('waiter_id')) {
            $waiterId = (int) $request->waiter_id;
            $query->whereHas('order', fn ($o) => $o->where('waiter_id', $waiterId));
        }

        // 7. Text Search
        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function (Builder $q) use ($term) {
                $q->where('kot_number', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%")
                        ->orWhereHas('table', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('waiter', fn ($w) => $w->where('name', 'like', "%{$term}%")))
                    ->orWhereHas('kitchen', fn ($k) => $k->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('items.menuItem', fn ($m) => $m->where('name', 'like', "%{$term}%"));
            });
        }

        // 8. Date Range / Presets
        $preset = $request->input('date_preset', 'all_time');
        $date = $request->input('date');
        $from = $request->input('date_from');
        $to = $request->input('date_to');

        switch ($preset) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'yesterday':
                $query->whereDate('created_at', Carbon::yesterday());
                break;
            case 'this_week':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case 'last_30_days':
                $query->whereDate('created_at', '>=', Carbon::now()->subDays(30));
                break;
            case 'single_date':
                if ($date) {
                    $query->whereDate('created_at', Carbon::parse($date));
                }
                break;
            case 'custom':
                if ($from && $to) {
                    $query->whereBetween('created_at', [
                        Carbon::parse($from)->startOfDay(),
                        Carbon::parse($to)->endOfDay(),
                    ]);
                } elseif ($from) {
                    $query->whereDate('created_at', '>=', Carbon::parse($from));
                } elseif ($to) {
                    $query->whereDate('created_at', '<=', Carbon::parse($to));
                }
                break;
            case 'all_time':
            default:
                break;
        }

        return $query;
    }

    /**
     * Core query builder that applies all filter parameters and multi-tenant scoping.
     */
    private function buildFilteredOrderQuery(Request $request): Builder
    {
        $query = Order::query();
        $this->applyBranchScope($query);

        $user = $this->actor();
        $isSuper = $this->isSuperAdmin();
        $isCompanyAdmin = $this->isCompanyAdmin();
        $locksBranch = $this->locksBranch();

        // 1. Company Filter
        if ($isSuper && $request->filled('company_id')) {
            $query->where('company_id', (int) $request->company_id);
        } elseif (! $isSuper && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        // 2. Branch Filter
        if ($locksBranch) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        }

        // 3. Kitchen Filter (orders having dishes or KOT tickets routed to this kitchen)
        if ($request->filled('kitchen_id')) {
            $kitchenId = (int) $request->kitchen_id;
            $query->whereHas('kotTickets', fn ($k) => $k->where('kitchen_id', $kitchenId));
        }

        // 4. Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // 5. Table Filter
        if ($request->filled('table_id')) {
            $query->where('table_id', (int) $request->table_id);
        }

        // 6. Waiter Filter
        if ($request->filled('waiter_id')) {
            $query->where('waiter_id', (int) $request->waiter_id);
        }

        // 7. Text Search
        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            $query->where(function (Builder $q) use ($term) {
                $q->where('order_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%")
                    ->orWhereHas('table', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('waiter', fn ($w) => $w->where('name', 'like', "%{$term}%"));
            });
        }

        // 8. Date Range / Presets
        $preset = $request->input('date_preset', 'all_time');
        $date = $request->input('date');
        $from = $request->input('date_from');
        $to = $request->input('date_to');

        switch ($preset) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'yesterday':
                $query->whereDate('created_at', Carbon::yesterday());
                break;
            case 'this_week':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case 'last_30_days':
                $query->whereDate('created_at', '>=', Carbon::now()->subDays(30));
                break;
            case 'single_date':
                if ($date) {
                    $query->whereDate('created_at', Carbon::parse($date));
                }
                break;
            case 'custom':
                if ($from && $to) {
                    $query->whereBetween('created_at', [
                        Carbon::parse($from)->startOfDay(),
                        Carbon::parse($to)->endOfDay(),
                    ]);
                } elseif ($from) {
                    $query->whereDate('created_at', '>=', Carbon::parse($from));
                } elseif ($to) {
                    $query->whereDate('created_at', '<=', Carbon::parse($to));
                }
                break;
            case 'all_time':
            default:
                // No date restriction: all orders are included by default
                break;
        }

        return $query;
    }

    /**
     * Resolve dropdown filter lists based on current user scope.
     */
    private function resolveFilterOptions(Request $request): array
    {
        $user = $this->actor();
        $isSuper = $this->isSuperAdmin();
        $locksBranch = $this->locksBranch();

        $companyId = $isSuper
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $branchId = $locksBranch
            ? (int) $user->branch_id
            : ($request->filled('branch_id') ? (int) $request->branch_id : null);

        // Companies
        $companies = $isSuper
            ? Company::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
            : collect([$user->company])->filter();

        // Branches
        $branchQuery = Branch::where('is_active', true);
        if ($companyId) {
            $branchQuery->where('company_id', $companyId);
        }
        if ($locksBranch) {
            $branchQuery->where('id', $user->branch_id);
        }
        $branches = $branchQuery->orderBy('name')->get(['id', 'name', 'code', 'company_id']);

        // Kitchens
        $kitchenQuery = Kitchen::where('is_active', true);
        if ($companyId) {
            $kitchenQuery->where('company_id', $companyId);
        }
        if ($branchId) {
            $kitchenQuery->where('branch_id', $branchId);
        } elseif ($locksBranch) {
            $kitchenQuery->where('branch_id', $user->branch_id);
        }
        $kitchens = $kitchenQuery->orderBy('name')->get(['id', 'name', 'code', 'branch_id']);

        // Tables
        $tableQuery = RestaurantTable::where('is_active', true);
        if ($branchId) {
            $tableQuery->where('branch_id', $branchId);
        } elseif ($locksBranch) {
            $tableQuery->where('branch_id', $user->branch_id);
        }
        $tables = $tableQuery->orderBy('name')->get(['id', 'name', 'branch_id']);

        // Waiters / Staff
        $waiterQuery = User::where('is_active', true);
        if ($companyId) {
            $waiterQuery->where('company_id', $companyId);
        }
        if ($branchId) {
            $waiterQuery->where('branch_id', $branchId);
        } elseif ($locksBranch) {
            $waiterQuery->where('branch_id', $user->branch_id);
        }
        $waiters = $waiterQuery->orderBy('name')->get(['id', 'name', 'branch_id']);

        return [
            'companies' => $companies,
            'branches' => $branches,
            'kitchens' => $kitchens,
            'tables' => $tables,
            'waiters' => $waiters,
            'isSuper' => $isSuper,
            'locksBranch' => $locksBranch,
            'selectedCompanyId' => $companyId,
            'selectedBranchId' => $branchId,
            'selectedKitchenId' => $request->input('kitchen_id'),
        ];
    }
}

