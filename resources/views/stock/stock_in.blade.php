@extends('layouts.app')

@section('title', 'Kitchen Stock In - Prepared Dish Production & Daily Intake')

@section('content')
<div class="kitchen-stockin-page">
    {{-- Alerts --}}
    @include('partials.alerts')

    {{-- Dashcart Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill bg-emerald-subtle text-emerald border border-emerald-subtle px-2.5 py-1 fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                    Section 7.1 Production
                </span>
                <span class="text-secondary small">&bull; Prepared Dish Inventory</span>
            </div>
            <h1 class="h3 fw-bold text-dark mb-1" style="letter-spacing: -0.02em; font-size: 1.65rem;">
                Kitchen Stock In
            </h1>
            <p class="text-secondary small mb-0" style="font-size: 0.85rem;">
                Enter opening baseline stock, record newly prepared batches cooked in kitchen stations, and view live available quantities.
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            @if(!empty($locksBranch))
                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                    <i class="bi bi-geo-alt-fill me-1"></i>{{ auth()->user()->branch?->name ?? 'Assigned Branch' }}
                </span>
            @else
                <span class="badge rounded-pill bg-light text-secondary border px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                    <i class="bi bi-buildings me-1"></i>All Branches
                </span>
            @endif

            <span class="badge rounded-pill bg-emerald-subtle text-emerald border border-emerald-subtle px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.75rem;">
                <span class="live-pulse-dot"></span>
                <span>Waiter App Synced</span>
            </span>

            @if($canManageStock)
                <button
                    type="button"
                    class="btn btn-sm btn-primary rounded-pill px-3.5 py-1.5 fw-semibold shadow-sm d-inline-flex align-items-center gap-1.5"
                    id="saveAllBatchesBtn"
                    onclick="submitBatchStockIn()"
                >
                    <i class="bi bi-cloud-arrow-up-fill"></i>
                    <span>Save All Batches</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Dashcart 4 Compact KPI Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Prepared Dishes --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="dashcart-stat-label">Dishes in Station</span>
                    <div class="dashcart-icon-circle bg-sky-subtle text-sky">
                        <i class="bi bi-cup-hot"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between">
                    <span class="dashcart-stat-number text-dark" id="stat-total-dishes">{{ number_format($summary['total_dishes'] ?? 0) }}</span>
                    <span class="text-secondary small" style="font-size: 0.75rem;">Assigned station</span>
                </div>
            </div>
        </div>

        {{-- Opening Stock Initialized --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="dashcart-stat-label">Opening Stock Set</span>
                    <div class="dashcart-icon-circle bg-purple-subtle text-purple">
                        <i class="bi bi-sunrise"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between">
                    <span class="dashcart-stat-number text-purple" id="stat-opening-set">{{ number_format($summary['opening_stock_set'] ?? 0) }}</span>
                    <span class="text-secondary small" style="font-size: 0.75rem;">Service baselines set</span>
                </div>
            </div>
        </div>

        {{-- Stock Added Today (Stock In) --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="dashcart-stat-label">Stock In Added Today</span>
                    <div class="dashcart-icon-circle bg-emerald-subtle text-emerald">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between">
                    <span class="dashcart-stat-number text-emerald" id="stat-stock-in-today">+{{ number_format($summary['total_stock_in_today'] ?? 0) }}</span>
                    <span class="text-secondary small" style="font-size: 0.75rem;">Portions cooked</span>
                </div>
            </div>
        </div>

        {{-- Available Ready Portions --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="dashcart-stat-label">Total Ready Stock</span>
                    <div class="dashcart-icon-circle bg-amber-subtle text-amber">
                        <i class="bi bi-boxes"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between">
                    <span class="dashcart-stat-number text-dark" id="stat-ready-portions">{{ number_format($summary['total_ready_portions'] ?? 0) }}</span>
                    <span class="text-secondary small" style="font-size: 0.75rem;">Available portions</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar & Quick Status Filter Tabs --}}
    <div class="card border-0 shadow-sm dashcart-card p-3 mb-4">
        <form method="GET" action="{{ route('stock.stock-in') }}" id="stockInFilterForm" class="row g-2.5 align-items-center">
            {{-- Search Dish --}}
            <div class="col-12 col-md-4 col-lg-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="text"
                        name="search"
                        class="form-control border-start-0 ps-0"
                        placeholder="Search dish name, code..."
                        value="{{ $searchTerm ?? '' }}"
                    >
                </div>
            </div>

            {{-- Kitchen Station Filter --}}
            <div class="col-6 col-md-3 col-lg-2">
                <select name="kitchen_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Kitchen Stations</option>
                    @foreach($kitchens as $k)
                        <option value="{{ $k->id }}" {{ ($selectedKitchenId == $k->id) ? 'selected' : '' }}>
                            {{ $k->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Category Filter --}}
            <div class="col-6 col-md-3 col-lg-2">
                <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" {{ ($selectedCategoryId == $c->id) ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Branch (if Admin) --}}
            @if(empty($locksBranch) && count($branches) > 1)
                <div class="col-6 col-md-3 col-lg-2">
                    <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Branches</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ ($selectedBranchId == $b->id) ? 'selected' : '' }}>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Stock Filter Preset --}}
            <div class="col-6 col-md-3 col-lg-2">
                <select name="stock_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" {{ ($stockFilter ?? 'all') === 'all' ? 'selected' : '' }}>All Stock Levels</option>
                    <option value="low_stock" {{ ($stockFilter ?? '') === 'low_stock' ? 'selected' : '' }}>Low Stock (≤5)</option>
                    <option value="out_of_stock" {{ ($stockFilter ?? '') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock (0)</option>
                    <option value="pending_opening" {{ ($stockFilter ?? '') === 'pending_opening' ? 'selected' : '' }}>Pending Opening Stock</option>
                </select>
            </div>

            {{-- Action Buttons --}}
            <div class="col-12 col-lg text-lg-end d-flex align-items-center justify-content-end gap-1.5 mt-2 mt-lg-0">
                <button type="submit" class="btn btn-sm btn-dark px-3 fw-semibold">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                @if(!empty($searchTerm) || !empty($selectedKitchenId) || !empty($selectedCategoryId) || (!empty($stockFilter) && $stockFilter !== 'all') || !empty($selectedBranchId))
                    <a href="{{ route('stock.stock-in') }}" class="btn btn-sm btn-light border px-2.5 text-secondary" title="Clear Filters">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- The Kitchen Stock In Production Table --}}
    <div class="card border-0 shadow-sm dashcart-card overflow-hidden mb-4">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
            <div>
                <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem;">
                    Prepared Dish Stock In Table
                </h2>
                <span class="text-secondary small" style="font-size: 0.78rem;">
                    Matches Section 7.1 specification &bull;
                    <span class="fw-semibold text-dark">Dish</span> |
                    <span class="fw-semibold text-purple">Opening Stock</span> |
                    <span class="fw-semibold text-emerald">Stock Added</span> |
                    <span class="fw-semibold text-dark">Total Available Stock</span>
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="text-secondary small" style="font-size: 0.75rem;">
                    Showing <strong>{{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }}</strong> of <strong>{{ $items->total() }}</strong> dishes
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 dashcart-table" id="stockInTable">
                <thead>
                    <tr>
                        <th class="ps-4 py-3" style="width: 28%; min-width: 240px;">Dish Details</th>
                        <th class="py-3 text-center" style="width: 14%; min-width: 130px;">
                            <span class="text-purple">Opening Stock</span>
                            <i class="bi bi-info-circle ms-0.5 text-muted" title="Baseline morning prep stock recorded for service" style="font-size: 0.72rem;"></i>
                        </th>
                        <th class="py-3 text-center" style="width: 28%; min-width: 250px;">
                            <span class="text-emerald">Stock Added (Stock In)</span>
                            <i class="bi bi-info-circle ms-0.5 text-muted" title="Portions prepared & added to kitchen stock today" style="font-size: 0.72rem;"></i>
                        </th>
                        <th class="py-3 text-center" style="width: 16%; min-width: 140px;">
                            <span>Total Available Stock</span>
                        </th>
                        <th class="pe-4 py-3 text-end" style="width: 14%; min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $dish)
                        @php
                            $isTracked = (bool) $dish->stock_tracking_enabled;
                            $stockQty = $dish->prepared_stock_quantity;
                            $hasStock = $stockQty !== null;
                            $isLow = $isTracked && $hasStock && $stockQty <= 5 && $stockQty > 0;
                            $isOut = $isTracked && $hasStock && $stockQty <= 0;
                            $avail = $dish->availability;
                            $kitchensList = $dish->kitchenMenuItems->pluck('kitchen.name')->filter()->unique();

                            // Today's values
                            $todayAdded = (int) ($todayStockIn[$dish->id] ?? 0);
                            $openingBaseline = isset($todayOpeningStock[$dish->id])
                                ? (int) $todayOpeningStock[$dish->id]
                                : (isset($latestHistoricalOpening[$dish->id]) ? (int) $latestHistoricalOpening[$dish->id] : null);
                        @endphp
                        <tr id="dish-row-{{ $dish->id }}" class="stockin-row" data-dish-id="{{ $dish->id }}" data-dish-name="{{ $dish->name }}">
                            {{-- 1. Dish Details --}}
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="dashcart-avatar">
                                        {{ strtoupper(substr($dish->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.92rem; line-height: 1.25;">
                                            {{ $dish->name }}
                                        </div>
                                        <div class="text-secondary small mt-0.5">
                                            <span class="font-monospace text-muted">{{ $dish->code ?? 'NO-CODE' }}</span>
                                            &bull; <span class="badge bg-light text-dark border px-1.5 py-0.5" style="font-size: 0.68rem;">{{ $dish->category?->name ?? 'General' }}</span>
                                            @if($dish->branch)
                                                &bull; <span class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-geo-alt"></i>{{ $dish->branch->name }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-1">
                                            @forelse($kitchensList as $kName)
                                                <span class="badge bg-emerald-subtle text-emerald border border-emerald-subtle me-1" style="font-size: 0.67rem;">
                                                    <i class="bi bi-fire me-0.5"></i>{{ $kName }}
                                                </span>
                                            @empty
                                                <span class="badge bg-light text-muted border" style="font-size: 0.67rem;">General Kitchen</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- 2. Opening Stock --}}
                            <td class="py-3 text-center">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <div class="fw-bold fs-6 text-purple" id="opening-stock-display-{{ $dish->id }}">
                                        @if($openingBaseline !== null)
                                            {{ $openingBaseline }} <span class="text-secondary fw-normal small" style="font-size: 0.72rem;">portions</span>
                                        @else
                                            <span class="text-muted small fw-normal">Not Set (0)</span>
                                        @endif
                                    </div>

                                    @if($canManageStock)
                                        <button
                                            type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none text-purple small mt-0.5"
                                            style="font-size: 0.72rem;"
                                            onclick="openOpeningStockModal({{ $dish->id }}, '{{ addslashes($dish->name) }}', {{ (int) ($openingBaseline ?? 0) }})"
                                        >
                                            <i class="bi bi-pencil-square me-0.5"></i>{{ $openingBaseline !== null ? 'Edit Baseline' : 'Set Baseline' }}
                                        </button>
                                    @endif
                                </div>
                            </td>

                            {{-- 3. Stock Added (Stock In) Entry Widget --}}
                            <td class="py-3 text-center">
                                <div class="d-flex flex-column align-items-center">
                                    {{-- Today's Total Added Badge --}}
                                    <div class="mb-1.5">
                                        <span class="badge rounded-pill bg-emerald-subtle text-emerald border border-emerald-subtle px-2.5 py-0.5 fw-bold" id="today-added-badge-{{ $dish->id }}" style="font-size: 0.72rem;">
                                            <i class="bi bi-box-arrow-in-down me-1"></i>+<span id="today-added-num-{{ $dish->id }}">{{ $todayAdded }}</span> today
                                        </span>
                                    </div>

                                    @if($canManageStock)
                                        {{-- Quick Add Input & Chips --}}
                                        <div class="stock-input-wrapper w-100" style="max-width: 220px;">
                                            <div class="input-group input-group-sm mb-1">
                                                <input
                                                    type="number"
                                                    class="form-control text-center fw-bold stock-qty-input"
                                                    id="qty-input-{{ $dish->id }}"
                                                    data-dish-id="{{ $dish->id }}"
                                                    min="1"
                                                    max="9999"
                                                    placeholder="+ Qty"
                                                    style="font-size: 0.85rem;"
                                                    onkeyup="if(event.key === 'Enter') submitSingleStockIn({{ $dish->id }})"
                                                >
                                                <button
                                                    type="button"
                                                    class="btn btn-emerald text-white fw-semibold d-inline-flex align-items-center gap-1"
                                                    id="btn-stock-in-{{ $dish->id }}"
                                                    onclick="submitSingleStockIn({{ $dish->id }})"
                                                    title="Add freshly prepared portions to stock"
                                                >
                                                    <i class="bi bi-plus-lg"></i>
                                                    <span>Add</span>
                                                </button>
                                            </div>

                                            {{-- Quick Chips (+5, +10, +20, +50) --}}
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button" class="btn btn-xs quick-chip" onclick="setQuickQty({{ $dish->id }}, 5)">+5</button>
                                                <button type="button" class="btn btn-xs quick-chip" onclick="setQuickQty({{ $dish->id }}, 10)">+10</button>
                                                <button type="button" class="btn btn-xs quick-chip" onclick="setQuickQty({{ $dish->id }}, 20)">+20</button>
                                                <button type="button" class="btn btn-xs quick-chip" onclick="setQuickQty({{ $dish->id }}, 50)">+50</button>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted small">View Only</span>
                                    @endif
                                </div>
                            </td>

                            {{-- 4. Total Available Stock --}}
                            <td class="py-3 text-center">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="d-flex align-items-baseline gap-1">
                                        <span class="fw-bolder fs-5 text-dark" id="available-qty-{{ $dish->id }}">
                                            {{ $stockQty ?? 0 }}
                                        </span>
                                        <span class="text-secondary small" style="font-size: 0.72rem;">portions</span>
                                    </div>

                                    <div class="mt-1" id="status-pill-{{ $dish->id }}">
                                        @if(!$isTracked || $stockQty === null)
                                            <span class="dashcart-pill pill-low" style="font-size: 0.7rem;">
                                                <span class="pill-dot dot-yellow"></span> Untracked
                                            </span>
                                        @elseif($avail === 'sold_out' || $stockQty <= 0)
                                            <span class="dashcart-pill pill-out" style="font-size: 0.7rem;">
                                                <span class="pill-dot dot-red"></span> Sold Out
                                            </span>
                                        @elseif($stockQty <= 5)
                                            <span class="dashcart-pill pill-low" style="font-size: 0.7rem;">
                                                <span class="pill-dot dot-yellow"></span> Low Stock ({{ $stockQty }})
                                            </span>
                                        @else
                                            <span class="dashcart-pill pill-in" style="font-size: 0.7rem;">
                                                <span class="pill-dot dot-green"></span> Available
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- 5. Actions --}}
                            <td class="pe-4 py-3 text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1"
                                        style="font-size: 0.75rem;"
                                        onclick="openHistoryModal({{ $dish->id }}, '{{ addslashes($dish->name) }}')"
                                        title="View Stock Transactions & Production Ledger"
                                    >
                                        <i class="bi bi-clock-history me-1"></i>History
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-5 text-center text-secondary">
                                <div class="py-4">
                                    <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                                    <h5 class="fw-bold text-dark mb-1">No prepared dishes found</h5>
                                    <p class="text-secondary small mb-3">No menu items match your permitted kitchen station or filter criteria.</p>
                                    @if(!empty($searchTerm) || !empty($selectedKitchenId) || !empty($selectedCategoryId) || (!empty($stockFilter) && $stockFilter !== 'all'))
                                        <a href="{{ route('stock.stock-in') }}" class="btn btn-sm btn-dark px-3 rounded-pill">
                                            Clear All Filters
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($items->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 border-top">
                <span class="text-secondary small">
                    Showing {{ $items->firstItem() }} to {{ $items->lastItem() }} of {{ $items->total() }} prepared dishes
                </span>
                <div>
                    {{ $items->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

{{-- MODAL 1: SET OPENING STOCK --}}
<div class="modal fade" id="openingStockModal" tabindex="-1" aria-labelledby="openingStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="dashcart-icon-circle bg-purple-subtle text-purple" style="width: 38px; height: 38px; font-size: 1.1rem;">
                        <i class="bi bi-sunrise"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6" id="openingStockModalLabel">
                            Set Opening Stock Baseline
                        </h5>
                        <span class="text-secondary small" style="font-size: 0.75rem;">Daily morning prep stock baseline entry</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="openingStockForm" onsubmit="handleOpeningStockSubmit(event)">
                <input type="hidden" id="openingDishId" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-light border small mb-3 p-2.5 rounded-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-secondary">Prepared Dish:</span>
                            <span class="fw-bold text-dark" id="openingDishName">—</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-secondary">Current Stock:</span>
                            <span class="fw-bold text-purple" id="openingDishCurrentVal">0 portions</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            Opening Stock Quantity <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                type="number"
                                class="form-control fw-bold fs-5 text-center"
                                id="openingQuantityInput"
                                min="0"
                                max="99999"
                                placeholder="e.g. 25"
                                required
                            >
                            <span class="input-group-text bg-light text-secondary">portions</span>
                        </div>
                        <span class="text-secondary small mt-1 d-block" style="font-size: 0.72rem;">
                            Sets the opening baseline quantity available before service starts.
                        </span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            Reason / Shift Note
                        </label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="openingReasonInput"
                            placeholder="e.g. Morning kitchen shift baseline preparation"
                            value="Morning kitchen prep service opening baseline"
                            maxlength="250"
                        >
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-sm btn-light border px-3 rounded-pill" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-sm btn-purple px-4 rounded-pill fw-semibold text-white" id="saveOpeningBtn">
                        <i class="bi bi-check-lg me-1"></i>Save Opening Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Shared Stock Modals (Adjust, Availability, History) --}}
@include('stock.partials.modals')

{{-- TOAST NOTIFICATION --}}
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="stockInToast" class="toast align-items-center text-white border-0 shadow-lg rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2 py-2.5 px-3">
                <i class="bi bi-check-circle-fill fs-5" id="toastIcon"></i>
                <div>
                    <strong class="d-block" id="toastTitle" style="font-size: 0.88rem;">Success</strong>
                    <span class="small" id="toastMessage" style="font-size: 0.8rem;">Stock updated successfully.</span>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<style>
/* Dashcart Theme Enhancements */
.kitchen-stockin-page {
    color: #1e293b;
}

.dashcart-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #eef2f6 !important;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.dashcart-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04) !important;
}

.dashcart-stat-label {
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    font-weight: 700;
}

.dashcart-stat-number {
    font-size: 1.85rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1.1;
}

.dashcart-icon-circle {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.bg-emerald-subtle { background-color: #ecfdf5 !important; }
.text-emerald { color: #10b981 !important; }
.border-emerald-subtle { border-color: #a7f3d0 !important; }
.btn-emerald { background-color: #10b981; border-color: #10b981; color: #fff; }
.btn-emerald:hover { background-color: #059669; border-color: #059669; color: #fff; }

.bg-sky-subtle { background-color: #f0f9ff !important; }
.text-sky { color: #0284c7 !important; }

.bg-purple-subtle { background-color: #faf5ff !important; }
.text-purple { color: #7c3aed !important; }
.btn-purple { background-color: #7c3aed; border-color: #7c3aed; color: #fff; }
.btn-purple:hover { background-color: #6d28d9; border-color: #6d28d9; color: #fff; }

.bg-amber-subtle { background-color: #fffbeb !important; }
.text-amber { color: #d97706 !important; }

.bg-rose-subtle { background-color: #fff1f2 !important; }
.text-rose { color: #e11d48 !important; }

.dashcart-avatar {
    width: 38px;
    height: 38px;
    border-radius: 9px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    letter-spacing: 0.02em;
    flex-shrink: 0;
}

/* Status Pills */
.dashcart-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.3;
}

.pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.pill-in { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.dot-green { background-color: #10b981; }

.pill-low { background-color: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.dot-yellow { background-color: #f59e0b; }

.pill-out { background-color: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
.dot-red { background-color: #f43f5e; }

/* Table styling */
.dashcart-table {
    border-collapse: separate;
    border-spacing: 0;
}

.dashcart-table thead th {
    background-color: #f8fafc;
    color: #64748b;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid #eef2f6;
    border-top: 0;
}

.dashcart-table tbody td {
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.86rem;
}

.dashcart-table tbody tr:hover td {
    background-color: #f8fafc;
}

/* Row Flash Highlight */
@keyframes rowSuccessPulse {
    0% { background-color: #d1fae5; }
    50% { background-color: #ecfdf5; }
    100% { background-color: transparent; }
}

.row-success-flash td {
    animation: rowSuccessPulse 1.8s ease;
}

/* Quick Chips */
.quick-chip {
    padding: 1px 7px;
    font-size: 0.7rem;
    font-weight: 600;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    transition: all 0.15s ease;
}

.quick-chip:hover {
    background: #10b981;
    color: #ffffff;
    border-color: #10b981;
}

.live-pulse-dot {
    width: 7px;
    height: 7px;
    background-color: #10b981;
    border-radius: 50%;
    display: inline-block;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: pulseGlow 1.8s infinite;
}

@keyframes pulseGlow {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
</style>

<script>
const CSRF_TOKEN = '{{ csrf_token() }}';

// Set quick quantity chips (+5, +10, etc.)
function setQuickQty(dishId, qty) {
    const input = document.getElementById('qty-input-' + dishId);
    if (!input) return;
    const currentVal = parseInt(input.value) || 0;
    input.value = currentVal + qty;
    input.focus();
}

// 1. Submit Single Dish Stock In
function submitSingleStockIn(dishId) {
    const input = document.getElementById('qty-input-' + dishId);
    const btn = document.getElementById('btn-stock-in-' + dishId);
    const qty = parseInt(input?.value);

    if (!qty || qty <= 0) {
        showToast('warning', 'Please enter a valid portion quantity (e.g. 10).');
        input?.focus();
        return;
    }

    const originalBtnHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

    fetch(`/stock/${dishId}/stock-in`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            quantity: qty,
            reason: 'Fresh kitchen batch cooked and added to stock'
        })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalBtnHtml;

        if (data.success) {
            // Update available stock display
            const availEl = document.getElementById('available-qty-' + dishId);
            if (availEl) {
                availEl.textContent = data.prepared_stock_quantity;
            }

            // Update today's added count
            const todayAddedNum = document.getElementById('today-added-num-' + dishId);
            if (todayAddedNum) {
                todayAddedNum.textContent = data.today_stock_in;
            }

            // Update status pill
            updateDishStatusPill(dishId, data.prepared_stock_quantity, data.availability);

            // Clear input
            input.value = '';

            // Flash row
            flashRowSuccess(dishId);

            // Update header KPIs
            refreshHeaderKpis(qty);

            showToast('success', data.message || `+${qty} portions added to stock.`);
        } else {
            showToast('danger', data.message || 'Failed to add stock.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalBtnHtml;
        console.error('Stock In error:', err);
        showToast('danger', 'A network error occurred while updating stock.');
    });
}

// 2. Open Set Opening Stock Modal
let openingModalInstance = null;
function openOpeningStockModal(dishId, dishName, currentOpening) {
    document.getElementById('openingDishId').value = dishId;
    document.getElementById('openingDishName').textContent = dishName;
    document.getElementById('openingDishCurrentVal').textContent = currentOpening + ' portions';
    document.getElementById('openingQuantityInput').value = currentOpening > 0 ? currentOpening : '';

    if (!openingModalInstance) {
        openingModalInstance = new bootstrap.Modal(document.getElementById('openingStockModal'));
    }
    openingModalInstance.show();
    setTimeout(() => {
        document.getElementById('openingQuantityInput').focus();
    }, 300);
}

// Handle Opening Stock Submit
function handleOpeningStockSubmit(e) {
    e.preventDefault();
    const dishId = document.getElementById('openingDishId').value;
    const qty = parseInt(document.getElementById('openingQuantityInput').value);
    const reason = document.getElementById('openingReasonInput').value;
    const saveBtn = document.getElementById('saveOpeningBtn');

    if (isNaN(qty) || qty < 0) {
        showToast('warning', 'Please enter a valid opening baseline (0 or higher).');
        return;
    }

    const originalBtnText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    fetch(`/stock/${dishId}/opening-stock`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            quantity: qty,
            reason: reason
        })
    })
    .then(res => res.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;

        if (data.success) {
            openingModalInstance.hide();

            // Update opening stock display in table
            const openEl = document.getElementById('opening-stock-display-' + dishId);
            if (openEl) {
                openEl.innerHTML = `${data.opening_stock} <span class="text-secondary fw-normal small" style="font-size: 0.72rem;">portions</span>`;
            }

            // Update available stock
            const availEl = document.getElementById('available-qty-' + dishId);
            if (availEl) {
                availEl.textContent = data.prepared_stock_quantity;
            }

            // Update status pill
            updateDishStatusPill(dishId, data.prepared_stock_quantity, data.availability);

            // Flash row
            flashRowSuccess(dishId);

            showToast('success', data.message || 'Opening stock set successfully.');
        } else {
            showToast('danger', data.message || 'Failed to set opening stock.');
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
        console.error('Opening stock error:', err);
        showToast('danger', 'Error updating opening stock.');
    });
}

// 3. Batch Stock In (Bulk Save across rows)
function submitBatchStockIn() {
    const inputs = document.querySelectorAll('.stock-qty-input');
    const batches = [];

    inputs.forEach(inp => {
        const qty = parseInt(inp.value);
        const dishId = parseInt(inp.getAttribute('data-dish-id'));
        if (qty && qty > 0 && dishId) {
            batches.push({
                menu_item_id: dishId,
                quantity: qty,
                reason: 'Kitchen batch production intake'
            });
        }
    });

    if (batches.length === 0) {
        showToast('warning', 'Please enter portion quantities for at least one dish before saving.');
        return;
    }

    const saveBtn = document.getElementById('saveAllBatchesBtn');
    const originalBtnHtml = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving All...';

    fetch('/stock/batch-stock-in', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ batches: batches })
    })
    .then(res => res.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;

        if (data.success && data.results) {
            data.results.forEach(item => {
                const availEl = document.getElementById('available-qty-' + item.id);
                if (availEl) availEl.textContent = item.prepared_stock_quantity;

                const todayAddedNum = document.getElementById('today-added-num-' + item.id);
                if (todayAddedNum) todayAddedNum.textContent = item.today_stock_in;

                updateDishStatusPill(item.id, item.prepared_stock_quantity, item.availability);

                const inp = document.getElementById('qty-input-' + item.id);
                if (inp) inp.value = '';

                flashRowSuccess(item.id);
            });

            refreshHeaderKpis(data.total_portions);
            showToast('success', data.message || 'All batches saved successfully.');
        } else {
            showToast('danger', data.message || 'Error saving batches.');
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
        console.error('Batch Stock In error:', err);
        showToast('danger', 'A network error occurred during batch stock intake.');
    });
}

// Helper: Update dish status pill dynamically
function updateDishStatusPill(dishId, stockQty, availability) {
    const pillContainer = document.getElementById('status-pill-' + dishId);
    if (!pillContainer) return;

    if (availability === 'sold_out' || stockQty <= 0) {
        pillContainer.innerHTML = `<span class="dashcart-pill pill-out" style="font-size: 0.7rem;"><span class="pill-dot dot-red"></span> Sold Out</span>`;
    } else if (stockQty <= 5) {
        pillContainer.innerHTML = `<span class="dashcart-pill pill-low" style="font-size: 0.7rem;"><span class="pill-dot dot-yellow"></span> Low Stock (${stockQty})</span>`;
    } else {
        pillContainer.innerHTML = `<span class="dashcart-pill pill-in" style="font-size: 0.7rem;"><span class="pill-dot dot-green"></span> Available</span>`;
    }
}

// Helper: Flash row with green highlight
function flashRowSuccess(dishId) {
    const row = document.getElementById('dish-row-' + dishId);
    if (row) {
        row.classList.remove('row-success-flash');
        void row.offsetWidth; // Trigger reflow
        row.classList.add('row-success-flash');
    }
}

// Helper: Update Header KPI numbers dynamically
function refreshHeaderKpis(addedPortions) {
    const stockInTodayEl = document.getElementById('stat-stock-in-today');
    if (stockInTodayEl) {
        const current = parseInt(stockInTodayEl.textContent.replace(/[^\d]/g, '')) || 0;
        stockInTodayEl.textContent = '+' + (current + addedPortions).toLocaleString();
    }

    const readyPortionsEl = document.getElementById('stat-ready-portions');
    if (readyPortionsEl) {
        const current = parseInt(readyPortionsEl.textContent.replace(/[^\d]/g, '')) || 0;
        readyPortionsEl.textContent = (current + addedPortions).toLocaleString();
    }
}

// Helper: Toast Notifications
let toastInstance = null;
function showToast(type, message) {
    const toastEl = document.getElementById('stockInToast');
    const toastTitle = document.getElementById('toastTitle');
    const toastMsg = document.getElementById('toastMessage');
    const toastIcon = document.getElementById('toastIcon');

    toastEl.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-dark');

    if (type === 'success') {
        toastEl.classList.add('bg-success');
        toastTitle.textContent = 'Stock Updated';
        toastIcon.className = 'bi bi-check-circle-fill fs-5';
    } else if (type === 'danger') {
        toastEl.classList.add('bg-danger');
        toastTitle.textContent = 'Error';
        toastIcon.className = 'bi bi-exclamation-triangle-fill fs-5';
    } else {
        toastEl.classList.add('bg-warning');
        toastTitle.textContent = 'Notice';
        toastIcon.className = 'bi bi-info-circle-fill fs-5 text-dark';
    }

    toastMsg.textContent = message;

    if (!toastInstance) {
        toastInstance = new bootstrap.Toast(toastEl, { delay: 3500 });
    }
    toastInstance.show();
}
</script>
@endsection

