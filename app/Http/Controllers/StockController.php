<?php

namespace App\Http\Controllers;

use App\Events\MenuAvailabilityChanged;
use App\Http\Controllers\Concerns\ManagesTenantAccess;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Kitchen;
use App\Models\MenuItem;
use App\Models\StockTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockController extends Controller
{
    use ManagesTenantAccess;

    /**
     * Helper to resolve tenant and role-based stock filter context.
     */
    private function resolveStockContext(Request $request): array
    {
        $user = $this->actor();
        $isSuper = $this->isSuperAdmin();
        $isCompanyAdmin = $this->isCompanyAdmin();
        $isAdmin = $isSuper || $isCompanyAdmin || $user->hasRole('admin') || $user->hasRole('super-admin');
        $locksBranch = $this->locksBranch();

        // Multi-tenant resolution
        $selectedCompanyId = $isSuper
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $selectedBranchId = $locksBranch
            ? (int) $user->branch_id
            : ($request->filled('branch_id') ? (int) $request->branch_id : null);

        $selectedKitchenId = $request->filled('kitchen_id') ? (int) $request->kitchen_id : null;
        $selectedCategoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $searchTerm = trim((string) $request->input('search'));

        // Kitchen staff specific scoping
        $assignedKitchenIds = [];
        if (! $isAdmin && ! $user->isManager() && Schema::hasTable('kitchen_user')) {
            try {
                if ($user->kitchens()->exists()) {
                    $assignedKitchenIds = $user->kitchens->pluck('id')->all();
                    if ($selectedKitchenId && ! in_array($selectedKitchenId, $assignedKitchenIds, true)) {
                        $selectedKitchenId = null;
                    }
                }
            } catch (\Throwable $e) {
                $assignedKitchenIds = [];
            }
        }

        // Dropdown choices
        $companies = $isSuper
            ? Company::query()->where('is_active', true)->orderBy('name')->get()
            : collect([$user->company])->filter();

        $branchQuery = Branch::query()->where('is_active', true);
        if ($selectedCompanyId) {
            $branchQuery->where('company_id', $selectedCompanyId);
        }
        if ($locksBranch) {
            $branchQuery->where('id', $user->branch_id);
        }
        $branches = $branchQuery->orderBy('name')->get();

        $kitchenQuery = Kitchen::query()->where('is_active', true);
        if ($selectedCompanyId) {
            $kitchenQuery->where('company_id', $selectedCompanyId);
        }
        if ($selectedBranchId) {
            $kitchenQuery->where('branch_id', $selectedBranchId);
        } elseif ($locksBranch) {
            $kitchenQuery->where('branch_id', $user->branch_id);
        }
        if (! empty($assignedKitchenIds)) {
            $kitchenQuery->whereIn('id', $assignedKitchenIds);
        }
        $kitchens = $kitchenQuery->orderBy('name')->get();

        $categoryQuery = Category::query()->where('is_active', true);
        if ($selectedCompanyId) {
            $categoryQuery->where('company_id', $selectedCompanyId);
        }
        if ($selectedBranchId) {
            $categoryQuery->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $selectedBranchId));
        } elseif ($locksBranch) {
            $categoryQuery->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $user->branch_id));
        }
        $categories = $categoryQuery->orderBy('name')->get();

        return [
            'user' => $user,
            'isSuper' => $isSuper,
            'isCompanyAdmin' => $isCompanyAdmin,
            'isAdmin' => $isAdmin,
            'locksBranch' => $locksBranch,
            'selectedCompanyId' => $selectedCompanyId,
            'selectedBranchId' => $selectedBranchId,
            'selectedKitchenId' => $selectedKitchenId,
            'selectedCategoryId' => $selectedCategoryId,
            'searchTerm' => $searchTerm,
            'assignedKitchenIds' => $assignedKitchenIds,
            'companies' => $companies,
            'branches' => $branches,
            'kitchens' => $kitchens,
            'categories' => $categories,
            'canManageStock' => $user->hasPermission('menu-item.stock-adjustment'),
        ];
    }

    /**
     * Build base query for Menu Items applying tenant and kitchen scoping.
     */
    private function buildMenuItemBaseQuery(array $ctx)
    {
        $query = MenuItem::query()
            ->with([
                'company:id,name',
                'branch:id,name',
                'category:id,name',
                'menuType:id,name',
                'kitchenMenuItems' => fn ($q) => $q->where('is_active', true)->with('kitchen:id,name,branch_id'),
            ])
            ->where('is_active', true);

        if ($ctx['selectedCompanyId']) {
            $query->where('company_id', $ctx['selectedCompanyId']);
        }
        if ($ctx['selectedBranchId']) {
            $query->where('branch_id', $ctx['selectedBranchId']);
        } elseif ($ctx['locksBranch']) {
            $query->where('branch_id', $ctx['user']->branch_id);
        }

        if ($ctx['selectedKitchenId']) {
            $query->whereHas('kitchenMenuItems', fn ($q) => $q->where('kitchen_id', $ctx['selectedKitchenId'])->where('is_active', true));
        } elseif (! empty($ctx['assignedKitchenIds'])) {
            $query->whereHas('kitchenMenuItems', fn ($q) => $q->whereIn('kitchen_id', $ctx['assignedKitchenIds'])->where('is_active', true));
        }

        if ($ctx['selectedCategoryId']) {
            $query->where('category_id', $ctx['selectedCategoryId']);
        }

        if ($ctx['searchTerm'] !== '') {
            $term = $ctx['searchTerm'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Compute summary counts across the current tenant scope.
     */
    private function computeStockSummary($baseQuery): array
    {
        $b = clone $baseQuery;
        return [
            'total_dishes' => (clone $b)->count(),
            'tracked_dishes' => (clone $b)->where('stock_tracking_enabled', true)->count(),
            'total_portions' => (int) (clone $b)->where('stock_tracking_enabled', true)->sum('prepared_stock_quantity'),
            'low_stock' => (clone $b)->where('stock_tracking_enabled', true)
                ->where('prepared_stock_quantity', '<=', 5)
                ->where('prepared_stock_quantity', '>', 0)
                ->count(),
            'out_of_stock' => (clone $b)->where('stock_tracking_enabled', true)
                ->where(function ($q) {
                    $q->whereNull('prepared_stock_quantity')->orWhere('prepared_stock_quantity', '<=', 0);
                })->count(),
            'sold_out' => (clone $b)->where('availability', 'sold_out')->count(),
            'discrepancies' => (clone $b)->where(function ($q) {
                // Dish marked available but has 0 stock, or marked sold_out with positive stock
                $q->where(function ($sub) {
                    $sub->where('stock_tracking_enabled', true)
                        ->where('availability', 'available')
                        ->where(function ($q2) {
                            $q2->whereNull('prepared_stock_quantity')->orWhere('prepared_stock_quantity', '<=', 0);
                        });
                })->orWhere(function ($sub) {
                    $sub->where('stock_tracking_enabled', true)
                        ->where('availability', 'sold_out')
                        ->where('prepared_stock_quantity', '>', 0);
                });
            })->count(),
        ];
    }

    // =========================================================================
    // SUB-MODULE 1: STOCK OVERVIEW
    // =========================================================================

    public function index(Request $request): View
    {
        return $this->stockInView($request);
    }

    public function overview(Request $request): View
    {
        return $this->stockInView($request);
    }

    // =========================================================================
    // SUB-MODULE: KITCHEN STOCK IN (SECTION 7.1)
    // =========================================================================

    public function stockInView(Request $request): View
    {
        $ctx = $this->resolveStockContext($request);
        $baseQuery = $this->buildMenuItemBaseQuery($ctx);

        // Precompute counts for status tabs
        $countsQuery = clone $baseQuery;
        $totalCount = (clone $countsQuery)->count();

        // Filter by Stock Status if requested
        $query = clone $baseQuery;
        $stockFilter = $request->input('stock_filter', 'all');
        if ($stockFilter === 'low_stock') {
            $query->where('stock_tracking_enabled', true)
                ->where('prepared_stock_quantity', '<=', 5)
                ->where('prepared_stock_quantity', '>', 0);
        } elseif ($stockFilter === 'out_of_stock') {
            $query->where('stock_tracking_enabled', true)
                ->where(function ($q) {
                    $q->whereNull('prepared_stock_quantity')->orWhere('prepared_stock_quantity', '<=', 0);
                });
        } elseif ($stockFilter === 'pending_opening') {
            $query->where(function ($q) {
                $q->where('stock_tracking_enabled', false)
                    ->orWhereNull('prepared_stock_quantity');
            });
        }

        $items = $query->orderBy('name')->paginate(25)->withQueryString();
        $itemIds = $items->pluck('id')->all();

        // 1. Fetch Today's Stock Added (Stock In) for each dish
        $todayStockIn = StockTransaction::query()
            ->whereDate('created_at', Carbon::today())
            ->where('transaction_type', 'stock_in')
            ->whereIn('menu_item_id', $itemIds)
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, SUM(quantity) as total_added')
            ->pluck('total_added', 'menu_item_id');

        // 2. Fetch Opening Stock for each dish
        $todayOpeningStock = StockTransaction::query()
            ->whereDate('created_at', Carbon::today())
            ->where('transaction_type', 'opening_stock')
            ->whereIn('menu_item_id', $itemIds)
            ->pluck('quantity', 'menu_item_id');

        $latestHistoricalOpening = StockTransaction::query()
            ->where('transaction_type', 'opening_stock')
            ->whereIn('menu_item_id', $itemIds)
            ->latest('id')
            ->get()
            ->unique('menu_item_id')
            ->pluck('quantity', 'menu_item_id');

        // 3. Compute Stock In Page KPIs across the tenant scope
        $scopeDishIds = (clone $baseQuery)->pluck('id')->all();
        $totalStockInToday = (int) StockTransaction::query()
            ->whereDate('created_at', Carbon::today())
            ->where('transaction_type', 'stock_in')
            ->whereIn('menu_item_id', $scopeDishIds)
            ->sum('quantity');

        $totalOpeningStockToday = (int) StockTransaction::query()
            ->whereDate('created_at', Carbon::today())
            ->where('transaction_type', 'opening_stock')
            ->whereIn('menu_item_id', $scopeDishIds)
            ->sum('quantity');

        $totalReadyPortions = (int) (clone $baseQuery)
            ->where('stock_tracking_enabled', true)
            ->sum('prepared_stock_quantity');

        $summary = [
            'total_dishes' => count($scopeDishIds),
            'opening_stock_set' => count($todayOpeningStock),
            'total_stock_in_today' => $totalStockInToday,
            'total_ready_portions' => $totalReadyPortions,
        ];

        return view('stock.stock_in', array_merge($ctx, [
            'items' => $items,
            'todayStockIn' => $todayStockIn,
            'todayOpeningStock' => $todayOpeningStock,
            'latestHistoricalOpening' => $latestHistoricalOpening,
            'summary' => $summary,
            'stockFilter' => $stockFilter,
            'totalCount' => $totalCount,
            'activeSubmodule' => 'stock-in',
        ]));
    }

    // =========================================================================
    // SUB-MODULE 2: STOCK VALIDATION
    // =========================================================================

    public function validation(Request $request): View
    {
        $ctx = $this->resolveStockContext($request);
        $query = $this->buildMenuItemBaseQuery($ctx);

        $validationStatus = $request->input('validation_status', 'all');

        switch ($validationStatus) {
            case 'healthy':
                $query->where('stock_tracking_enabled', true)
                    ->where('prepared_stock_quantity', '>', 5)
                    ->where('availability', 'available');
                break;
            case 'warning':
                $query->where('stock_tracking_enabled', true)
                    ->where('prepared_stock_quantity', '<=', 5)
                    ->where('prepared_stock_quantity', '>', 0);
                break;
            case 'critical':
                $query->where('stock_tracking_enabled', true)
                    ->where(function ($q) {
                        $q->whereNull('prepared_stock_quantity')->orWhere('prepared_stock_quantity', '<=', 0);
                    });
                break;
            case 'discrepancy':
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('stock_tracking_enabled', true)
                            ->where('availability', 'available')
                            ->where(function ($q2) {
                                $q2->whereNull('prepared_stock_quantity')->orWhere('prepared_stock_quantity', '<=', 0);
                            });
                    })->orWhere(function ($sub) {
                        $sub->where('stock_tracking_enabled', true)
                            ->where('availability', 'sold_out')
                            ->where('prepared_stock_quantity', '>', 0);
                    });
                });
                break;
            case 'untracked':
                $query->where('stock_tracking_enabled', false);
                break;
        }

        $summary = $this->computeStockSummary($this->buildMenuItemBaseQuery($ctx));
        $items = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('stock.validation', array_merge($ctx, [
            'items' => $items,
            'summary' => $summary,
            'validationStatus' => $validationStatus,
            'activeSubmodule' => 'validation',
        ]));
    }

    // =========================================================================
    // SUB-MODULE 3: STOCK ADJUSTMENT
    // =========================================================================

    public function adjustments(Request $request): View
    {
        $ctx = $this->resolveStockContext($request);
        $summary = $this->computeStockSummary($this->buildMenuItemBaseQuery($ctx));

        // Get dishes available for adjustment in user's tenant scope
        $dishes = $this->buildMenuItemBaseQuery($ctx)
            ->where('stock_tracking_enabled', true)
            ->with(['kitchenMenuItems' => fn ($q) => $q->where('is_active', true)->with('kitchen:id,name,branch_id')])
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'prepared_stock_quantity', 'availability', 'branch_id']);

        // Recent adjustments performed today
        $recentAdjustmentsQuery = StockTransaction::query()
            ->with(['menuItem:id,name,code,branch_id', 'user:id,name'])
            ->whereIn('transaction_type', ['stock_in', 'wastage', 'damaged', 'manual_adjustment', 'opening_stock'])
            ->latest()
            ->limit(20);

        if ($ctx['selectedBranchId']) {
            $recentAdjustmentsQuery->where('branch_id', $ctx['selectedBranchId']);
        } elseif ($ctx['locksBranch']) {
            $recentAdjustmentsQuery->where('branch_id', $ctx['user']->branch_id);
        } elseif ($ctx['selectedCompanyId']) {
            $recentAdjustmentsQuery->where('company_id', $ctx['selectedCompanyId']);
        }

        $recentAdjustments = $recentAdjustmentsQuery->get();

        // Calculate today's adjustment totals
        $todayAdjBase = StockTransaction::query()->whereDate('created_at', Carbon::today());
        if ($ctx['selectedBranchId']) {
            $todayAdjBase->where('branch_id', $ctx['selectedBranchId']);
        } elseif ($ctx['locksBranch']) {
            $todayAdjBase->where('branch_id', $ctx['user']->branch_id);
        }

        $todayStats = [
            'stock_in_portions' => (int) (clone $todayAdjBase)->where('transaction_type', 'stock_in')->sum('quantity'),
            'wastage_portions' => (int) (clone $todayAdjBase)->where('transaction_type', 'wastage')->sum('quantity'),
            'damaged_portions' => (int) (clone $todayAdjBase)->where('transaction_type', 'damaged')->sum('quantity'),
            'reconciled_count' => (int) (clone $todayAdjBase)->where('transaction_type', 'manual_adjustment')->count(),
        ];

        return view('stock.adjustments', array_merge($ctx, [
            'dishes' => $dishes,
            'summary' => $summary,
            'recentAdjustments' => $recentAdjustments,
            'todayStats' => $todayStats,
            'activeSubmodule' => 'adjustments',
        ]));
    }

    // =========================================================================
    // SUB-MODULE 4: STOCK TRANSACTION HISTORY
    // =========================================================================

    public function history(Request $request): View
    {
        $ctx = $this->resolveStockContext($request);
        $summary = $this->computeStockSummary($this->buildMenuItemBaseQuery($ctx));

        $txQuery = StockTransaction::query()
            ->with([
                'menuItem:id,name,code,branch_id,category_id',
                'menuItem.branch:id,name',
                'menuItem.category:id,name',
                'user:id,name',
                'order:id,order_number',
            ])
            ->latest();

        if ($ctx['selectedBranchId']) {
            $txQuery->where('branch_id', $ctx['selectedBranchId']);
        } elseif ($ctx['locksBranch']) {
            $txQuery->where('branch_id', $ctx['user']->branch_id);
        } elseif ($ctx['selectedCompanyId']) {
            $txQuery->where('company_id', $ctx['selectedCompanyId']);
        }

        // Filter by Transaction Type
        $selectedType = $request->input('transaction_type');
        if ($selectedType && $selectedType !== 'all') {
            $txQuery->where('transaction_type', $selectedType);
        }

        // Filter by Date Preset
        $datePreset = $request->input('date_preset', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        switch ($datePreset) {
            case 'today':
                $txQuery->whereDate('created_at', Carbon::today());
                break;
            case 'yesterday':
                $txQuery->whereDate('created_at', Carbon::yesterday());
                break;
            case 'this_week':
                $txQuery->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'this_month':
                $txQuery->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case 'custom':
                if ($dateFrom && $dateTo) {
                    $txQuery->whereBetween('created_at', [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()]);
                } elseif ($dateFrom) {
                    $txQuery->whereDate('created_at', '>=', Carbon::parse($dateFrom));
                }
                break;
        }

        // Search in Dish Name or Order Number
        if ($ctx['searchTerm'] !== '') {
            $term = $ctx['searchTerm'];
            $txQuery->where(function ($q) use ($term) {
                $q->whereHas('menuItem', fn ($m) => $m->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%"))
                    ->orWhere('reason', 'like', "%{$term}%");
            });
        }

        $transactions = $txQuery->paginate(20)->withQueryString();

        return view('stock.history', array_merge($ctx, [
            'transactions' => $transactions,
            'summary' => $summary,
            'selectedType' => $selectedType,
            'datePreset' => $datePreset,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'activeSubmodule' => 'history',
        ]));
    }

    // =========================================================================
    // SUB-MODULE 5: DISH AVAILABILITY (86 BOARD)
    // =========================================================================

    public function availabilityBoard(Request $request): View
    {
        $ctx = $this->resolveStockContext($request);
        $baseQuery = $this->buildMenuItemBaseQuery($ctx);

        // Precompute counts for quick tabs across the tenant/filter scope
        $countsQuery = clone $baseQuery;
        $totalCount = (clone $countsQuery)->count();
        $availableCount = (clone $countsQuery)->where('availability', 'available')->count();
        $tempCount = (clone $countsQuery)->where('availability', 'temporarily_unavailable')->count();
        $soldOutCount = (clone $countsQuery)->where('availability', 'sold_out')->count();

        $query = clone $baseQuery;

        $selectedAvailability = $request->input('availability');
        if ($selectedAvailability && in_array($selectedAvailability, ['available', 'temporarily_unavailable', 'sold_out'], true)) {
            $query->where('availability', $selectedAvailability);
        }

        $selectedStockStatus = $request->input('stock_status');
        if ($selectedStockStatus) {
            switch ($selectedStockStatus) {
                case 'in_stock':
                    $query->where('stock_tracking_enabled', true)->where('prepared_stock_quantity', '>', 5);
                    break;
                case 'low_stock':
                    $query->where('stock_tracking_enabled', true)
                        ->where('prepared_stock_quantity', '<=', 5)
                        ->where('prepared_stock_quantity', '>', 0);
                    break;
                case 'out_of_stock':
                    $query->where('stock_tracking_enabled', true)
                        ->where(function ($q) {
                            $q->whereNull('prepared_stock_quantity')->orWhere('prepared_stock_quantity', '<=', 0);
                        });
                    break;
                case 'untracked':
                    $query->where('stock_tracking_enabled', false);
                    break;
            }
        }

        $summary = $this->computeStockSummary($this->buildMenuItemBaseQuery($ctx));
        $items = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('stock.availability', array_merge($ctx, [
            'items' => $items,
            'summary' => $summary,
            'selectedAvailability' => $selectedAvailability,
            'selectedStockStatus' => $selectedStockStatus,
            'totalCount' => $totalCount,
            'availableCount' => $availableCount,
            'tempCount' => $tempCount,
            'soldOutCount' => $soldOutCount,
            'activeSubmodule' => 'availability',
        ]));
    }

    // =========================================================================
    // ACTION ENDPOINTS (USED ACROSS ALL SUB-MODULES)
    // =========================================================================

    /**
     * Set or update opening baseline stock for a prepared dish (Section 7.1).
     */
    public function setOpeningStock(Request $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $user = $this->actor();
        abort_unless($user->hasPermission('menu-item.stock-adjustment'), 403, 'You do not have permission to adjust stock.');
        $this->authorizeMenuItemAccess($user, $menuItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $quantity = (int) $validated['quantity'];
        $reason = trim((string) ($validated['reason'] ?? '')) ?: 'Kitchen service opening stock baseline';

        $updatedItem = DB::transaction(function () use ($menuItem, $user, $quantity, $reason) {
            $locked = MenuItem::query()->whereKey($menuItem->id)->lockForUpdate()->firstOrFail();
            $before = (int) ($locked->prepared_stock_quantity ?? 0);
            $delta = $quantity - $before;

            $locked->prepared_stock_quantity = $quantity;
            $locked->stock_tracking_enabled = true;
            if ($locked->availability === 'sold_out' && $quantity > 0) {
                $locked->availability = 'available';
            }
            $locked->save();

            StockTransaction::create([
                'company_id' => $locked->company_id,
                'branch_id' => $locked->branch_id,
                'menu_item_id' => $locked->id,
                'user_id' => $user->id,
                'transaction_type' => 'opening_stock',
                'quantity' => $quantity,
                'quantity_delta' => $delta,
                'stock_before' => $before,
                'stock_after' => $quantity,
                'reason' => $reason,
            ]);

            return $locked;
        });

        event(new MenuAvailabilityChanged(
            (int) $updatedItem->branch_id,
            (int) $updatedItem->id,
            (string) $updatedItem->availability,
            (int) $updatedItem->prepared_stock_quantity,
        ));

        $msg = "Opening stock for '{$updatedItem->name}' set to {$quantity} portion(s).";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'opening_stock' => $quantity,
                'prepared_stock_quantity' => $updatedItem->prepared_stock_quantity,
                'availability' => $updatedItem->availability,
                'status_label' => ucwords(str_replace('_', ' ', $updatedItem->availability)),
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Add newly prepared dish quantities (Stock In) (Section 7.1).
     */
    public function recordStockIn(Request $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $user = $this->actor();
        abort_unless($user->hasPermission('menu-item.stock-adjustment'), 403, 'You do not have permission to adjust stock.');
        $this->authorizeMenuItemAccess($user, $menuItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $addedQty = (int) $validated['quantity'];
        $reason = trim((string) ($validated['reason'] ?? '')) ?: 'Fresh kitchen batch cooked & added to stock';

        $updatedItem = DB::transaction(function () use ($menuItem, $user, $addedQty, $reason) {
            $locked = MenuItem::query()->whereKey($menuItem->id)->lockForUpdate()->firstOrFail();
            $before = (int) ($locked->prepared_stock_quantity ?? 0);
            $after = $before + $addedQty;

            $locked->prepared_stock_quantity = $after;
            $locked->stock_tracking_enabled = true;
            if ($locked->availability === 'sold_out' && $after > 0) {
                $locked->availability = 'available';
            }
            $locked->save();

            StockTransaction::create([
                'company_id' => $locked->company_id,
                'branch_id' => $locked->branch_id,
                'menu_item_id' => $locked->id,
                'user_id' => $user->id,
                'transaction_type' => 'stock_in',
                'quantity' => $addedQty,
                'quantity_delta' => $addedQty,
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $reason,
            ]);

            return $locked;
        });

        event(new MenuAvailabilityChanged(
            (int) $updatedItem->branch_id,
            (int) $updatedItem->id,
            (string) $updatedItem->availability,
            (int) $updatedItem->prepared_stock_quantity,
        ));

        // Recompute today's total stock in for this dish
        $todayTotal = (int) StockTransaction::query()
            ->whereDate('created_at', Carbon::today())
            ->where('transaction_type', 'stock_in')
            ->where('menu_item_id', $updatedItem->id)
            ->sum('quantity');

        $msg = "+{$addedQty} portion(s) added for '{$updatedItem->name}'. New available stock: {$updatedItem->prepared_stock_quantity}.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'added_quantity' => $addedQty,
                'today_stock_in' => $todayTotal,
                'prepared_stock_quantity' => $updatedItem->prepared_stock_quantity,
                'availability' => $updatedItem->availability,
                'status_label' => ucwords(str_replace('_', ' ', $updatedItem->availability)),
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Batch Stock In: Record production batches for multiple dishes simultaneously (Section 7.1).
     */
    public function batchStockIn(Request $request): RedirectResponse|JsonResponse
    {
        $user = $this->actor();
        abort_unless($user->hasPermission('menu-item.stock-adjustment'), 403, 'You do not have permission to adjust stock.');

        $validated = $request->validate([
            'batches' => ['required', 'array', 'min:1'],
            'batches.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'batches.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'batches.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        $results = [];
        $totalItems = 0;
        $totalPortions = 0;

        DB::transaction(function () use ($validated, $user, &$results, &$totalItems, &$totalPortions) {
            foreach ($validated['batches'] as $entry) {
                $menuItem = MenuItem::findOrFail($entry['menu_item_id']);
                $this->authorizeMenuItemAccess($user, $menuItem);

                $addedQty = (int) $entry['quantity'];
                $reason = trim((string) ($entry['reason'] ?? '')) ?: 'Kitchen batch production intake';

                $locked = MenuItem::query()->whereKey($menuItem->id)->lockForUpdate()->firstOrFail();
                $before = (int) ($locked->prepared_stock_quantity ?? 0);
                $after = $before + $addedQty;

                $locked->prepared_stock_quantity = $after;
                $locked->stock_tracking_enabled = true;
                if ($locked->availability === 'sold_out' && $after > 0) {
                    $locked->availability = 'available';
                }
                $locked->save();

                StockTransaction::create([
                    'company_id' => $locked->company_id,
                    'branch_id' => $locked->branch_id,
                    'menu_item_id' => $locked->id,
                    'user_id' => $user->id,
                    'transaction_type' => 'stock_in',
                    'quantity' => $addedQty,
                    'quantity_delta' => $addedQty,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'reason' => $reason,
                ]);

                event(new MenuAvailabilityChanged(
                    (int) $locked->branch_id,
                    (int) $locked->id,
                    (string) $locked->availability,
                    (int) $locked->prepared_stock_quantity,
                ));

                $todayTotal = (int) StockTransaction::query()
                    ->whereDate('created_at', Carbon::today())
                    ->where('transaction_type', 'stock_in')
                    ->where('menu_item_id', $locked->id)
                    ->sum('quantity');

                $results[] = [
                    'id' => $locked->id,
                    'name' => $locked->name,
                    'added_quantity' => $addedQty,
                    'today_stock_in' => $todayTotal,
                    'prepared_stock_quantity' => $after,
                    'availability' => $locked->availability,
                ];

                $totalItems++;
                $totalPortions += $addedQty;
            }
        });

        $msg = "Batch intake completed: {$totalPortions} portion(s) added across {$totalItems} dish(es).";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'results' => $results,
                'total_items' => $totalItems,
                'total_portions' => $totalPortions,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Adjust prepared stock for a menu item.
     * Reuses the existing logic from MobileKitchenController.
     */
    public function adjust(Request $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $user = $this->actor();
        abort_unless($user->hasPermission('menu-item.stock-adjustment'), 403, 'You do not have permission to adjust stock.');
        $this->authorizeMenuItemAccess($user, $menuItem);

        if ($request->input('type') === 'reduce_stock') {
            $request->merge([
                'type' => 'manual_adjustment',
                'direction' => 'decrease',
            ]);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:stock_in,wastage,damaged,manual_adjustment'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'direction' => ['required_if:type,manual_adjustment', 'nullable', 'in:increase,decrease'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $updatedItem = DB::transaction(function () use ($menuItem, $user, $validated) {
            $locked = MenuItem::query()->whereKey($menuItem->id)->lockForUpdate()->firstOrFail();
            $locked->stock_tracking_enabled = true;

            $wasConfigured = $locked->prepared_stock_quantity !== null;
            $before = (int) ($locked->prepared_stock_quantity ?? 0);
            $increases = $validated['type'] === 'stock_in'
                || ($validated['type'] === 'manual_adjustment' && ($validated['direction'] ?? 'increase') === 'increase');
            $delta = $increases ? (int) $validated['quantity'] : -(int) $validated['quantity'];
            $after = $before + $delta;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Stock cannot be reduced below zero.'],
                ]);
            }

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

            return $locked;
        });

        event(new MenuAvailabilityChanged(
            (int) $updatedItem->branch_id,
            (int) $updatedItem->id,
            (string) $updatedItem->availability,
            $updatedItem->prepared_stock_quantity === null ? null : (int) $updatedItem->prepared_stock_quantity,
        ));

        $msg = "Stock for '{$updatedItem->name}' adjusted successfully. New stock: {$updatedItem->prepared_stock_quantity} portion(s).";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'prepared_stock_quantity' => $updatedItem->prepared_stock_quantity,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Set dish availability (available, temporarily_unavailable, sold_out).
     * Reuses the existing logic from MobileKitchenController.
     */
    public function setAvailability(Request $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $user = $this->actor();
        abort_unless($user->hasPermission('menu-item.stock-adjustment'), 403, 'You do not have permission to update availability.');
        $this->authorizeMenuItemAccess($user, $menuItem);

        $validated = $request->validate([
            'availability' => ['required', 'string', 'in:available,temporarily_unavailable,sold_out'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $updatedItem = DB::transaction(function () use ($menuItem, $user, $validated) {
            $locked = MenuItem::query()->whereKey($menuItem->id)->lockForUpdate()->firstOrFail();
            if ($locked->availability === $validated['availability']) {
                return $locked;
            }

            $stock = (int) ($locked->prepared_stock_quantity ?? 0);
            $reasonText = ! empty(trim((string) ($validated['reason'] ?? '')))
                ? trim($validated['reason'])
                : ('Status updated to ' . ucwords(str_replace('_', ' ', $validated['availability'])) . ' via Dish Availability board');

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
                'reason' => $reasonText . ' (' . $locked->availability . ' → ' . $validated['availability'] . ')',
            ]);

            $locked->update(['availability' => $validated['availability']]);

            return $locked;
        });

        event(new MenuAvailabilityChanged(
            (int) $updatedItem->branch_id,
            (int) $updatedItem->id,
            (string) $updatedItem->availability,
            $updatedItem->prepared_stock_quantity === null ? null : (int) $updatedItem->prepared_stock_quantity,
        ));

        $statusLabel = ucwords(str_replace('_', ' ', $updatedItem->availability));
        $msg = "Availability for '{$updatedItem->name}' updated to '{$statusLabel}'.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'availability' => $updatedItem->availability,
                'availability_label' => $statusLabel,
                'updated_at_formatted' => $updatedItem->updated_at?->diffForHumans() ?? 'Just now',
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Toggle stock tracking for a dish.
     */
    public function toggleTracking(Request $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $user = $this->actor();
        abort_unless($user->hasPermission('menu-item.stock-adjustment'), 403, 'Permission denied.');
        $this->authorizeMenuItemAccess($user, $menuItem);

        $newTracking = ! $menuItem->stock_tracking_enabled;
        $menuItem->stock_tracking_enabled = $newTracking;
        if ($newTracking && $menuItem->prepared_stock_quantity === null) {
            $menuItem->prepared_stock_quantity = 0;
        }
        $menuItem->save();

        $msg = $newTracking
            ? "Stock tracking enabled for '{$menuItem->name}'."
            : "Stock tracking disabled for '{$menuItem->name}'.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'stock_tracking_enabled' => $newTracking,
                'prepared_stock_quantity' => $menuItem->prepared_stock_quantity,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Get stock transactions history for a menu item (JSON for modal).
     */
    public function transactions(Request $request, MenuItem $menuItem): JsonResponse
    {
        $user = $this->actor();
        $this->authorizeMenuItemAccess($user, $menuItem);

        $transactions = StockTransaction::query()
            ->where('menu_item_id', $menuItem->id)
            ->with(['user:id,name', 'order:id,order_number'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (StockTransaction $t) => [
                'id' => $t->id,
                'type' => $t->transaction_type,
                'type_label' => ucwords(str_replace('_', ' ', $t->transaction_type)),
                'quantity' => $t->quantity,
                'quantity_delta' => $t->quantity_delta,
                'stock_before' => $t->stock_before,
                'stock_after' => $t->stock_after,
                'order_number' => $t->order?->order_number,
                'reason' => $t->reason,
                'user_name' => $t->user?->name ?? 'System',
                'date' => $t->created_at?->format('d M Y, h:i A'),
                'time_ago' => $t->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'success' => true,
            'dish' => [
                'id' => $menuItem->id,
                'name' => $menuItem->name,
                'code' => $menuItem->code,
                'stock_tracking_enabled' => (bool) $menuItem->stock_tracking_enabled,
                'prepared_stock_quantity' => $menuItem->prepared_stock_quantity,
                'availability' => $menuItem->availability,
            ],
            'transactions' => $transactions,
        ]);
    }

    /**
     * Authorize access to the menu item based on branch and kitchen roles.
     */
    private function authorizeMenuItemAccess($user, MenuItem $menuItem): void
    {
        $this->authorizeBranch($menuItem->branch);

        if (! $this->isSuperAdmin() && ! $this->isCompanyAdmin() && ! $user->isManager() && $user->isKitchenStaff()) {
            if (Schema::hasTable('kitchen_user')) {
                $assignedKitchenIds = $user->kitchens()->pluck('kitchens.id')->all();
                abort_unless(
                    $menuItem->kitchenMenuItems()
                        ->whereIn('kitchen_id', $assignedKitchenIds)
                        ->where('is_active', true)
                        ->exists(),
                    403,
                    'You are not authorized to manage stock for this kitchen station.'
                );
            }
        }
    }
}
