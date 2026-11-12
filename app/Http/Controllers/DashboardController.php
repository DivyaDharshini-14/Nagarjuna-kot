<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Kitchen;
use App\Models\KotTicket;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ManagesTenantAccess;

    public function index(Request $request): View|JsonResponse
    {
        $user = $this->actor();

        // 1. Role-based tenant resolution
        // Admin: can view all branches and kitchens
        $isSuper = $this->isSuperAdmin();
        $isCompanyAdmin = $this->isCompanyAdmin();
        $isAdmin = $isSuper || $isCompanyAdmin || $user->hasRole('admin') || $user->hasRole('super-admin');

        // Branch Manager: can view only their assigned branch and its kitchens
        $isBranchManager = ! $isAdmin && ($user->isManager() || $user->hasRole('branch-manager') || (bool) $user->branch_id);
        $locksBranch = $isBranchManager;

        // Resolve Company filter
        $selectedCompanyId = null;
        if ($isSuper) {
            $selectedCompanyId = $request->filled('company_id') ? (int) $request->company_id : null;
        } else {
            $selectedCompanyId = (int) $user->company_id;
        }

        // Resolve Branch filter
        $selectedBranchId = null;
        if ($locksBranch) {
            // Branch manager is strictly locked to their assigned branch
            $selectedBranchId = (int) $user->branch_id;
        } elseif ($request->filled('branch_id')) {
            // Admin can choose a specific branch or leave null to view all branches
            $selectedBranchId = (int) $request->branch_id;
            if ($isCompanyAdmin && $selectedBranchId) {
                $valid = Branch::where('id', $selectedBranchId)->where('company_id', $selectedCompanyId)->exists();
                if (! $valid) {
                    $selectedBranchId = null;
                }
            }
        }

        // Resolve Kitchen filter
        $selectedKitchenId = $request->filled('kitchen_id') ? (int) $request->kitchen_id : null;

        // If user is restricted to specific kitchens (e.g. chef/kitchen staff)
        $userAssignedKitchenIds = [];
        if (! $isAdmin && ! $user->isManager() && Schema::hasTable('kitchen_user')) {
            try {
                if ($user->kitchens()->exists()) {
                    $userAssignedKitchenIds = $user->kitchens->pluck('id')->all();
                    if ($selectedKitchenId && ! in_array($selectedKitchenId, $userAssignedKitchenIds, true)) {
                        $selectedKitchenId = null;
                    }
                }
            } catch (\Throwable $e) {
                $userAssignedKitchenIds = [];
            }
        }

        // 2. Date Filtering Resolution
        $datePreset = $request->input('date_preset', 'today');
        $date = $request->input('date', Carbon::today()->toDateString());
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $selectedOrderNumber = trim((string) $request->input('order_number', ''));
        $selectedStatus = $request->input('status', '');

        // 3. Dropdown Options based on RBAC
        $companies = $isSuper
            ? Company::query()->where('is_active', true)->orderBy('name')->get()
            : collect([$user->company])->filter();

        $branchQuery = Branch::query()->where('is_active', true);
        if ($selectedCompanyId) {
            $branchQuery->where('company_id', $selectedCompanyId);
        }
        if ($locksBranch) {
            // Branch manager only gets their assigned branch
            $branchQuery->where('id', $user->branch_id);
        }
        $branches = $branchQuery->orderBy('name')->get();

        $kitchenQuery = Kitchen::query()->where('is_active', true);
        if ($selectedCompanyId) {
            $kitchenQuery->where('company_id', $selectedCompanyId);
        }
        if ($selectedBranchId) {
            // If branch is selected or locked to branch, only show kitchens of that branch
            $kitchenQuery->where('branch_id', $selectedBranchId);
        } elseif ($locksBranch) {
            $kitchenQuery->where('branch_id', $user->branch_id);
        }
        if (! empty($userAssignedKitchenIds)) {
            $kitchenQuery->whereIn('id', $userAssignedKitchenIds);
        }
        $kitchens = $kitchenQuery->orderBy('name')->get();

        // 4. Base Orders Query
        $baseOrderQuery = Order::query();

        if ($selectedCompanyId) {
            $baseOrderQuery->where('company_id', $selectedCompanyId);
        }
        if ($selectedBranchId) {
            $baseOrderQuery->where('branch_id', $selectedBranchId);
        } elseif ($locksBranch) {
            $baseOrderQuery->where('branch_id', $user->branch_id);
        }

        if ($selectedKitchenId) {
            $baseOrderQuery->whereHas('kotTickets', fn ($q) => $q->where('kitchen_id', $selectedKitchenId));
        } elseif (! empty($userAssignedKitchenIds)) {
            $baseOrderQuery->whereHas('kotTickets', fn ($q) => $q->whereIn('kitchen_id', $userAssignedKitchenIds));
        }

        // Apply date filtering to orders
        $this->applyDateFilter($baseOrderQuery, $datePreset, $date, $dateFrom, $dateTo);

        // 5. Dashboard Summary: 6 Cards
        $summary = [
            'total' => (clone $baseOrderQuery)->count(),
            'active' => (clone $baseOrderQuery)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'preparing' => (clone $baseOrderQuery)->where('status', 'preparing')->count(),
            'ready' => (clone $baseOrderQuery)->where('status', 'ready')->count(),
            'completed' => (clone $baseOrderQuery)->where('status', 'completed')->count(),
            'cancelled' => (clone $baseOrderQuery)->where('status', 'cancelled')->count(),
            'total_sales' => (clone $baseOrderQuery)->where('status', 'completed')->sum('total_amount'),
        ];

        // 6. Branch-wise Orders Breakdown
        $branchStatsQuery = (clone $baseOrderQuery)
            ->selectRaw("
                branch_id,
                COUNT(*) as total_count,
                SUM(CASE WHEN status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing_count,
                SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as sales_amount
            ")
            ->groupBy('branch_id');

        $branchStatsMap = $branchStatsQuery->get()->keyBy('branch_id');

        // Build array of branches with stats
        $branchBreakdown = $branches->map(function ($branch) use ($branchStatsMap, $summary) {
            $stat = $branchStatsMap->get($branch->id);
            $total = (int) ($stat?->total_count ?? 0);
            $active = (int) ($stat?->active_count ?? 0);
            $completed = (int) ($stat?->completed_count ?? 0);
            $cancelled = (int) ($stat?->cancelled_count ?? 0);
            $sales = (float) ($stat?->sales_amount ?? 0);
            $share = $summary['total'] > 0 ? round(($total / $summary['total']) * 100, 1) : 0;

            return [
                'branch' => $branch,
                'total' => $total,
                'active' => $active,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'sales' => $sales,
                'share' => $share,
            ];
        })->sortByDesc('total')->values();

        // 7. Kitchen-wise Pending Orders Breakdown
        $kotQuery = KotTicket::query();
        if ($selectedCompanyId) {
            $kotQuery->where('company_id', $selectedCompanyId);
        }
        if ($selectedBranchId) {
            $kotQuery->where('branch_id', $selectedBranchId);
        } elseif ($locksBranch) {
            $kotQuery->where('branch_id', $user->branch_id);
        }
        if ($selectedKitchenId) {
            $kotQuery->where('kitchen_id', $selectedKitchenId);
        } elseif (! empty($userAssignedKitchenIds)) {
            $kotQuery->whereIn('kitchen_id', $userAssignedKitchenIds);
        }

        $this->applyDateFilter($kotQuery, $datePreset, $date, $dateFrom, $dateTo);

        $kitchenStatsMap = $kotQuery
            ->selectRaw("
                kitchen_id,
                COUNT(*) as total_tickets,
                SUM(CASE WHEN ticket_status IN ('new', 'accepted', 'preparing') THEN 1 ELSE 0 END) as pending_tickets,
                SUM(CASE WHEN ticket_status IN ('new', 'accepted') THEN 1 ELSE 0 END) as new_tickets,
                SUM(CASE WHEN ticket_status = 'preparing' THEN 1 ELSE 0 END) as preparing_tickets,
                SUM(CASE WHEN ticket_status = 'ready' THEN 1 ELSE 0 END) as ready_tickets,
                SUM(CASE WHEN ticket_status = 'served' THEN 1 ELSE 0 END) as served_tickets,
                SUM(CASE WHEN ticket_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tickets
            ")
            ->groupBy('kitchen_id')
            ->get()
            ->keyBy('kitchen_id');

        $kitchenBreakdown = $kitchens->map(function ($kitchen) use ($kitchenStatsMap) {
            $stat = $kitchenStatsMap->get($kitchen->id);
            $pending = (int) ($stat?->pending_tickets ?? 0);
            $new = (int) ($stat?->new_tickets ?? 0);
            $preparing = (int) ($stat?->preparing_tickets ?? 0);
            $ready = (int) ($stat?->ready_tickets ?? 0);
            $total = (int) ($stat?->total_tickets ?? 0);

            // Compute load status
            $loadStatus = 'Clear';
            $loadColor = 'success';
            if ($pending >= 10) {
                $loadStatus = 'High Load';
                $loadColor = 'danger';
            } elseif ($pending >= 5) {
                $loadStatus = 'Moderate';
                $loadColor = 'warning';
            } elseif ($pending > 0) {
                $loadStatus = 'Normal';
                $loadColor = 'info';
            }

            return [
                'kitchen' => $kitchen,
                'pending' => $pending,
                'new' => $new,
                'preparing' => $preparing,
                'ready' => $ready,
                'total' => $total,
                'load_status' => $loadStatus,
                'load_color' => $loadColor,
            ];
        })->sortByDesc('pending')->values();

        // 8. Recent Live Orders (matching current filter)
        $recentOrdersQuery = (clone $baseOrderQuery)
            ->with(['table.floor', 'waiter', 'branch', 'kotTickets.kitchen']);

        if ($selectedOrderNumber !== '') {
            $recentOrdersQuery->where('order_number', 'like', "%{$selectedOrderNumber}%");
        }

        if ($selectedStatus !== '' && $selectedStatus !== 'all') {
            $recentOrdersQuery->where('status', $selectedStatus);
        }

        $recentOrders = $recentOrdersQuery
            ->latest()
            ->limit(10)
            ->get();

        // Status Distribution for Full-width Order Status Pipeline Card
        $statusTotal = max(1, $summary['total']);
        $statusDistribution = [
            'active' => [
                'count' => $summary['active'],
                'percentage' => $summary['total'] > 0 ? round(($summary['active'] / $statusTotal) * 100, 1) : 0,
                'color' => '#0284c7',
                'label' => 'Active',
            ],
            'preparing' => [
                'count' => $summary['preparing'],
                'percentage' => $summary['total'] > 0 ? round(($summary['preparing'] / $statusTotal) * 100, 1) : 0,
                'color' => '#f59e0b',
                'label' => 'Preparing',
            ],
            'ready' => [
                'count' => $summary['ready'],
                'percentage' => $summary['total'] > 0 ? round(($summary['ready'] / $statusTotal) * 100, 1) : 0,
                'color' => '#10b981',
                'label' => 'Ready',
            ],
            'completed' => [
                'count' => $summary['completed'],
                'percentage' => $summary['total'] > 0 ? round(($summary['completed'] / $statusTotal) * 100, 1) : 0,
                'color' => '#8b5cf6',
                'label' => 'Completed',
            ],
            'cancelled' => [
                'count' => $summary['cancelled'],
                'percentage' => $summary['total'] > 0 ? round(($summary['cancelled'] / $statusTotal) * 100, 1) : 0,
                'color' => '#ef4444',
                'label' => 'Cancelled',
            ],
        ];

        // 9. Yesterday Comparison for Total Orders Stat Card
        $yesterdayQuery = Order::query()->whereDate('created_at', Carbon::parse($date)->subDay());
        if ($selectedCompanyId) {
            $yesterdayQuery->where('company_id', $selectedCompanyId);
        }
        if ($selectedBranchId) {
            $yesterdayQuery->where('branch_id', $selectedBranchId);
        } elseif ($locksBranch) {
            $yesterdayQuery->where('branch_id', $user->branch_id);
        }
        $yesterdayTotal = $yesterdayQuery->count();
        $vsYesterdayPct = 0;
        if ($yesterdayTotal > 0) {
            $vsYesterdayPct = round((($summary['total'] - $yesterdayTotal) / $yesterdayTotal) * 100);
        } elseif ($summary['total'] > 0) {
            $vsYesterdayPct = 100;
        } else {
            $vsYesterdayPct = 0;
        }

        // 10. Hourly Order Trend Data (Today vs Yesterday for smooth Dashcart area line chart)
        $hourlyLabels = ['10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM'];
        $hourlyHours = [10, 11, 12, 13, 14, 15, 16, 17];
        $hourlyCounts = (clone $baseOrderQuery)
            ->selectRaw('HOUR(created_at) as hr, COUNT(*) as cnt')
            ->groupBy('hr')
            ->pluck('cnt', 'hr')
            ->all();

        $yesterdayHourlyCounts = (clone $yesterdayQuery)
            ->selectRaw('HOUR(created_at) as hr, COUNT(*) as cnt')
            ->groupBy('hr')
            ->pluck('cnt', 'hr')
            ->all();

        $hourlyTrend = [];
        $yesterdayHourlyTrend = [];
        foreach ($hourlyHours as $hr) {
            $hourlyTrend[] = (int) ($hourlyCounts[$hr] ?? 0);
            $yesterdayHourlyTrend[] = (int) ($yesterdayHourlyCounts[$hr] ?? 0);
        }

        // 11. Donut Chart Data for "Orders by cuisine / Kitchens" (matching Dashcart)
        $donutLabels = [];
        $donutData = [];
        $totalKitchenTickets = 0;
        foreach ($kitchenBreakdown as $kItem) {
            $kObj = is_array($kItem) ? $kItem['kitchen'] : $kItem->kitchen;
            $kPending = (int) (is_array($kItem) ? $kItem['pending'] : $kItem->pending);
            $kTotal = (int) (is_array($kItem) ? ($kItem['total'] ?? 0) : ($kItem->total ?? 0));
            $donutLabels[] = is_object($kObj) ? $kObj->name : ($kObj['name'] ?? 'Kitchen');
            $val = $kPending > 0 ? $kPending : $kTotal;
            $donutData[] = $val;
            $totalKitchenTickets += $val;
        }

        $maxBranchOrders = max(1, $branchBreakdown->max('total') ?? 1);

        // Real-time JSON endpoint for live background polling
        if ($request->ajax() || $request->wantsJson() || $request->input('format') === 'json') {
            return response()->json([
                'success' => true,
                'summary' => $summary,
                'vsYesterdayPct' => $vsYesterdayPct,
                'branchBreakdown' => $branchBreakdown->map(function ($item) {
                    $b = is_array($item) ? $item['branch'] : $item->branch;
                    return [
                        'name' => is_object($b) ? $b->name : ($b['name'] ?? 'Branch'),
                        'total' => is_array($item) ? $item['total'] : $item->total,
                        'active' => is_array($item) ? $item['active'] : $item->active,
                        'completed' => is_array($item) ? $item['completed'] : $item->completed,
                        'cancelled' => is_array($item) ? $item['cancelled'] : $item->cancelled,
                        'sales' => is_array($item) ? $item['sales'] : $item->sales,
                        'share' => is_array($item) ? $item['share'] : $item->share,
                    ];
                }),
                'kitchenBreakdown' => $kitchenBreakdown->map(function ($item) {
                    $k = is_array($item) ? $item['kitchen'] : $item->kitchen;
                    $kName = is_object($k) ? $k->name : ($k['name'] ?? 'Kitchen');
                    $bName = is_array($item)
                        ? ($item['branch_name'] ?? (is_object($k) ? $k->branch?->name : 'Main Branch'))
                        : ($item->kitchen->branch?->name ?? 'Main Branch');
                    return [
                        'name' => $kName,
                        'branch_name' => $bName ?? 'Main Branch',
                        'pending' => is_array($item) ? $item['pending'] : $item->pending,
                        'load_status' => is_array($item) ? $item['load_status'] : $item->load_status,
                        'load_color' => is_array($item) ? $item['load_color'] : $item->load_color,
                    ];
                }),
                'recentOrders' => $recentOrders->map(function ($ord) {
                    return [
                        'order_number' => $ord->order_number,
                        'table_name' => $ord->table?->name ?? 'T-01',
                        'branch_name' => $ord->branch?->name ?? 'Main Branch',
                        'status' => strtolower($ord->status ?? 'active'),
                        'total_amount' => number_format((float) ($ord->total_amount ?? 0), 2),
                        'time' => $ord->created_at ? $ord->created_at->format('h:i A') : '',
                    ];
                }),
                'hourlyLabels' => $hourlyLabels,
                'hourlyTrend' => $hourlyTrend,
                'yesterdayHourlyTrend' => $yesterdayHourlyTrend,
                'donutLabels' => $donutLabels,
                'donutData' => $donutData,
                'totalKitchenTickets' => $totalKitchenTickets,
                'statusDistribution' => $statusDistribution,
                'selectedOrderNumber' => $selectedOrderNumber,
                'selectedStatus' => $selectedStatus,
                'lastUpdated' => Carbon::now()->format('h:i:s A'),
            ]);
        }

        return view('dashboard', [
            'summary' => $summary,
            'branchBreakdown' => $branchBreakdown,
            'maxBranchOrders' => $maxBranchOrders,
            'kitchenBreakdown' => $kitchenBreakdown,
            'recentOrders' => $recentOrders,
            'vsYesterdayPct' => $vsYesterdayPct,
            'hourlyLabels' => $hourlyLabels,
            'hourlyTrend' => $hourlyTrend,
            'yesterdayHourlyTrend' => $yesterdayHourlyTrend,
            'donutLabels' => $donutLabels,
            'donutData' => $donutData,
            'totalKitchenTickets' => $totalKitchenTickets,
            'statusDistribution' => $statusDistribution,
            'selectedOrderNumber' => $selectedOrderNumber,
            'selectedStatus' => $selectedStatus,
            'companies' => $companies,
            'branches' => $branches,
            'kitchens' => $kitchens,
            'selectedCompanyId' => $selectedCompanyId,
            'selectedBranchId' => $selectedBranchId,
            'selectedKitchenId' => $selectedKitchenId,
            'datePreset' => $datePreset,
            'date' => $date,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'isSuper' => $isSuper,
            'isCompanyAdmin' => $isCompanyAdmin,
            'isAdmin' => $isAdmin,
            'isBranchManager' => $isBranchManager,
            'locksBranch' => $locksBranch,
        ]);
    }

    private function applyDateFilter($query, string $preset, ?string $date, ?string $from, ?string $to): void
    {
        switch ($preset) {
            case 'today':
                if ($date && $date !== Carbon::today()->toDateString()) {
                    $query->whereDate('created_at', Carbon::parse($date));
                } else {
                    $query->whereDate('created_at', Carbon::today());
                }
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
            case 'custom':
                if ($from && $to) {
                    $query->whereBetween('created_at', [
                        Carbon::parse($from)->startOfDay(),
                        Carbon::parse($to)->endOfDay(),
                    ]);
                } elseif ($from) {
                    $query->whereDate('created_at', '>=', Carbon::parse($from));
                }
                break;
            case 'single_date':
                if ($date) {
                    $query->whereDate('created_at', Carbon::parse($date));
                }
                break;
            case 'all_time':
                // No date restriction
                break;
            default:
                if ($date) {
                    $query->whereDate('created_at', Carbon::parse($date));
                } else {
                    $query->whereDate('created_at', Carbon::today());
                }
                break;
        }
    }
}

