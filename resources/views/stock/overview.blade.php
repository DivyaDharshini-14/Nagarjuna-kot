@extends('layouts.app')

@section('title', 'Stock Overview - Dashcart Prepared Dish Inventory')

@section('content')
<div class="dashcart-stock-page">
    {{-- Alerts --}}
    @include('partials.alerts')

    {{-- Dashcart Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 fw-bold text-dark mb-0" style="letter-spacing: -0.02em; font-size: 1.65rem;">
                    Stock Management
                </h1>
                <span class="badge rounded-pill bg-emerald-subtle text-emerald border border-emerald-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;">
                    Prepared Dish Inventory
                </span>
            </div>
            <p class="text-secondary small mb-0" style="font-size: 0.85rem;">
                Prepared dish portion counts, opening baseline, daily kitchen additions, and live availability tracking.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if(!empty($locksBranch))
                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                    <i class="bi bi-geo-alt-fill me-1"></i>{{ auth()->user()->branch?->name ?? 'Assigned Branch' }}
                </span>
            @else
                <span class="badge rounded-pill bg-light text-secondary border px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                    <i class="bi bi-buildings me-1"></i>All Branches
                </span>
            @endif

            @if($canManageStock)
                <button
                    type="button"
                    class="btn btn-sm btn-primary rounded-pill px-3.5 py-1.5 fw-semibold shadow-sm d-inline-flex align-items-center gap-1.5"
                    onclick="openStockInBatchModal()"
                >
                    <i class="bi bi-plus-lg"></i>
                    <span>Stock In (Batch)</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Stock Module Sub-Navigation --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4 pb-2 border-bottom" style="border-color: #eef2f6 !important;">
        <a href="{{ route('stock.overview') }}" class="btn btn-sm {{ request()->routeIs('stock.overview') || request()->routeIs('stock.index') ? 'btn-dark' : 'btn-light border text-secondary' }} rounded-pill px-3 py-1.5 fw-semibold shadow-none" style="font-size: 0.8rem;">
            <i class="bi bi-boxes me-1.5"></i>Stock Overview
        </a>
        <a href="{{ route('stock.stock-in') }}" class="btn btn-sm {{ request()->routeIs('stock.stock-in') ? 'btn-dark' : 'btn-light border text-secondary' }} rounded-pill px-3 py-1.5 fw-semibold shadow-none" style="font-size: 0.8rem;">
            <i class="bi bi-box-arrow-in-down me-1.5"></i>Kitchen Stock In
        </a>
        <a href="{{ route('stock.adjustments') }}" class="btn btn-sm {{ request()->routeIs('stock.adjustments') ? 'btn-dark' : 'btn-light border text-secondary' }} rounded-pill px-3 py-1.5 fw-semibold shadow-none" style="font-size: 0.8rem;">
            <i class="bi bi-sliders me-1.5"></i>Stock Adjustments
        </a>
        <a href="{{ route('stock.history') }}" class="btn btn-sm {{ request()->routeIs('stock.history') ? 'btn-dark' : 'btn-light border text-secondary' }} rounded-pill px-3 py-1.5 fw-semibold shadow-none" style="font-size: 0.8rem;">
            <i class="bi bi-journal-text me-1.5"></i>Transaction History
        </a>
        <a href="{{ route('stock.availability-board') }}" class="btn btn-sm {{ request()->routeIs('stock.availability-board') ? 'btn-dark' : 'btn-light border text-secondary' }} rounded-pill px-3 py-1.5 fw-semibold shadow-none" style="font-size: 0.8rem;">
            <i class="bi bi-toggle-on me-1.5"></i>Dish Availability
        </a>
        <a href="{{ route('stock.validation') }}" class="btn btn-sm {{ request()->routeIs('stock.validation') ? 'btn-dark' : 'btn-light border text-secondary' }} rounded-pill px-3 py-1.5 fw-semibold shadow-none" style="font-size: 0.8rem;">
            <i class="bi bi-shield-check me-1.5"></i>Validation Guard
        </a>
    </div>

    {{-- Dashcart 4 Compact KPI Summary Cards (Hidden) --}}
    <div class="row g-3 mb-4" style="display: none !important;">
        {{-- 1. Total Dishes --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Total Dishes</span>
                        <div class="dashcart-icon-circle bg-sky-subtle text-sky">
                            <i class="bi bi-cup-hot"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number text-dark">
                        {{ number_format($summary['total_dishes']) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        <strong>{{ $summary['tracked_dishes'] }}</strong> tracked for portions
                    </span>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: #0284c7; fill: none; stroke-width: 1.8;">
                            <path d="M0,10 Q15,4 30,12 T50,6"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Available Portions --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Available Portions</span>
                        <div class="dashcart-icon-circle bg-emerald-subtle text-emerald">
                            <i class="bi bi-boxes"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number text-emerald">
                        {{ number_format($summary['total_portions']) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        Ready in kitchen stations
                    </span>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: #10b981; fill: none; stroke-width: 1.8;">
                            <path d="M0,14 Q20,10 35,4 T50,8"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Low Stock Alert --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Low Stock Alert</span>
                        <div class="dashcart-icon-circle bg-amber-subtle text-amber">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number {{ $summary['low_stock'] > 0 ? 'text-amber' : 'text-dark' }}">
                        {{ number_format($summary['low_stock']) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        Threshold &le; 5 portions
                    </span>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: #f59e0b; fill: none; stroke-width: 1.8;">
                            <path d="M0,6 Q15,14 30,8 T50,14"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. Out of Stock / Sold Out --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Out of Stock</span>
                        <div class="dashcart-icon-circle bg-rose-subtle text-rose">
                            <i class="bi bi-slash-circle"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number {{ ($summary['out_of_stock'] + $summary['sold_out']) > 0 ? 'text-rose' : 'text-dark' }}">
                        {{ number_format($summary['out_of_stock'] + $summary['sold_out']) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        <strong>{{ $summary['sold_out'] }}</strong> marked sold out
                    </span>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: #ef4444; fill: none; stroke-width: 1.8;">
                            <path d="M0,4 Q20,14 35,6 T50,16"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Centralized Compact Filter Panel --}}
    <div class="card p-3.5 border-0 shadow-sm mb-4 dashcart-card">
        <form method="GET" action="{{ route('stock.overview') }}" id="stockFilterForm">
            <div class="row g-2.5 align-items-center">
                {{-- Search --}}
                <div class="col-12 col-md-5 col-xl-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted ps-3">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            type="search"
                            name="search"
                            class="form-control border-start-0 ps-1"
                            placeholder="Search dish name, code..."
                            value="{{ $searchTerm }}"
                        >
                    </div>
                </div>

                {{-- Company Selector (Super Admin) --}}
                @if($isSuper)
                    <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                        <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Companies</option>
                            @foreach($companies as $c)
                                <option value="{{ $c->id }}" @selected((string)$selectedCompanyId === (string)$c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Branch Selector --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    @if($locksBranch)
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted ps-2.5">
                                <i class="bi bi-lock-fill text-warning"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 bg-light text-muted fw-semibold" value="{{ auth()->user()->branch?->name }}" readonly>
                        </div>
                    @else
                        <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Branches</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" @selected((string)$selectedBranchId === (string)$b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                {{-- Kitchen Station --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    <select name="kitchen_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Kitchens</option>
                        @foreach($kitchens as $k)
                            <option value="{{ $k->id }}" @selected((string)$selectedKitchenId === (string)$k->id)>{{ $k->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Category --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string)$selectedCategoryId === (string)$cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Stock Status Filter --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    <select name="stock_status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Stock Levels</option>
                        <option value="in_stock" @selected($selectedStockStatus === 'in_stock')>In Stock (> 5)</option>
                        <option value="low_stock" @selected($selectedStockStatus === 'low_stock')>Low Stock (1-5)</option>
                        <option value="out_of_stock" @selected($selectedStockStatus === 'out_of_stock')>Out of Stock (0)</option>
                        <option value="tracked" @selected($selectedStockStatus === 'tracked')>Tracked Items</option>
                        <option value="untracked" @selected($selectedStockStatus === 'untracked')>Untracked Items</option>
                    </select>
                </div>

                {{-- Availability Filter --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    <select name="availability" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Availability</option>
                        <option value="available" @selected($selectedAvailability === 'available')>🟢 Available</option>
                        <option value="temporarily_unavailable" @selected($selectedAvailability === 'temporarily_unavailable')>🟡 Temp. Unavailable</option>
                        <option value="sold_out" @selected($selectedAvailability === 'sold_out')>🔴 Sold Out</option>
                    </select>
                </div>

                <div class="col-12 col-md-auto ms-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-filter me-1"></i>Apply Filter
                    </button>
                    <a href="{{ route('stock.overview') }}" class="btn btn-sm btn-light border px-3 rounded-pill fw-semibold text-secondary">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Dish-wise Stock Table (Dashcart Clean Table) --}}
    <div class="card p-0 border-0 shadow-sm mb-4 dashcart-card overflow-hidden">
        <div class="p-3.5 px-4 bg-white border-bottom d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2" style="border-color: #f1f5f9 !important;">
            <div>
                <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem;">
                    Prepared Dish Stock Overview
                </h2>
                <span class="text-secondary small" style="font-size: 0.78rem;">
                    Prepared dishes inventory &bull;
                    <span class="fw-semibold text-dark">Dish</span> |
                    <span class="fw-semibold text-purple">Opening Stock</span> |
                    <span class="fw-semibold text-emerald">Stock Added</span> |
                    <span class="fw-semibold text-dark">Available Stock</span>
                </span>
            </div>
            <div class="text-secondary small" style="font-size: 0.75rem;">
                Showing <strong>{{ $items->firstItem() ?? 0 }}</strong> to <strong>{{ $items->lastItem() ?? 0 }}</strong> of <strong>{{ $items->total() }}</strong> dishes
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.86rem;">
                <thead class="dashcart-thead">
                    <tr>
                        <th class="ps-4 py-3" style="width: 32%; min-width: 250px;">Dish / Item</th>
                        <th class="py-3 text-center" style="width: 15%; min-width: 130px;">
                            <span class="text-purple">Opening Stock</span>
                            <i class="bi bi-info-circle ms-0.5 text-muted" title="Initial available baseline quantity before service" style="font-size: 0.72rem;"></i>
                        </th>
                        <th class="py-3 text-center" style="width: 15%; min-width: 130px;">
                            <span class="text-emerald">Stock Added</span>
                            <i class="bi bi-info-circle ms-0.5 text-muted" title="Additional prepared-dish quantity added (Stock In batches)" style="font-size: 0.72rem;"></i>
                        </th>
                        <th class="py-3 text-center" style="width: 18%; min-width: 150px;">
                            <span class="text-dark">Available Stock</span>
                            <i class="bi bi-info-circle ms-0.5 text-muted" title="Current ready portions after additions and order deductions" style="font-size: 0.72rem;"></i>
                        </th>
                        <th class="pe-4 py-3 text-end" style="width: 20%; min-width: 210px;">Actions</th>
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
                            $kitchensList = $dish->kitchenMenuItems->pluck('kitchen.name')->filter()->unique();

                            // Today's values
                            $todayAdded = (int) ($todayStockIn[$dish->id] ?? 0);
                            $openingBaseline = isset($todayOpeningStock[$dish->id])
                                ? (int) $todayOpeningStock[$dish->id]
                                : (isset($latestHistoricalOpening[$dish->id]) ? (int) $latestHistoricalOpening[$dish->id] : null);
                        @endphp
                        <tr>
                            {{-- 1. Dish / Item Details --}}
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
                                    @if($openingBaseline !== null)
                                        <div class="fw-bold fs-6 text-purple">
                                            {{ $openingBaseline }} <span class="text-secondary fw-normal small" style="font-size: 0.72rem;">portions</span>
                                        </div>
                                        <span class="text-secondary small mt-0.5" style="font-size: 0.7rem;">Initial Available</span>
                                    @else
                                        <span class="text-muted small fw-normal">Not Set (0)</span>
                                        <span class="text-secondary small mt-0.5" style="font-size: 0.7rem;">Baseline</span>
                                    @endif
                                </div>
                            </td>

                            {{-- 3. Stock Added --}}
                            <td class="py-3 text-center">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    @if($todayAdded > 0)
                                        <span class="badge rounded-pill bg-emerald-subtle text-emerald border border-emerald-subtle px-2.5 py-1 fw-bold" style="font-size: 0.74rem;">
                                            <i class="bi bi-plus me-0.5"></i>{{ $todayAdded }} portions
                                        </span>
                                        <span class="text-secondary small mt-0.5" style="font-size: 0.7rem;">Added Today</span>
                                    @else
                                        <span class="text-muted small">0 portions</span>
                                        <span class="text-secondary small mt-0.5" style="font-size: 0.7rem;">No additions today</span>
                                    @endif
                                </div>
                            </td>

                            {{-- 4. Available Stock (Current Available Quantity) --}}
                            <td class="py-3 text-center">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    @if($isTracked && $hasStock)
                                        <div class="d-flex align-items-baseline gap-1">
                                            <span class="fw-bolder fs-5 {{ $isOut ? 'text-danger' : ($isLow ? 'text-amber' : 'text-emerald') }}">
                                                {{ max(0, (int)$stockQty) }}
                                            </span>
                                            <span class="text-secondary small" style="font-size: 0.72rem;">portions</span>
                                        </div>

                                        <div class="mt-1">
                                            @if($dish->availability === 'sold_out' || $stockQty <= 0)
                                                <span class="dashcart-pill pill-out" style="font-size: 0.7rem;">
                                                    <span class="pill-dot dot-red"></span> {{ $dish->availability === 'sold_out' ? 'Sold Out' : 'Out of Stock' }}
                                                </span>
                                            @elseif($isLow)
                                                <span class="dashcart-pill pill-low" style="font-size: 0.7rem;">
                                                    <span class="pill-dot dot-yellow"></span> Low Stock ({{ $stockQty }})
                                                </span>
                                            @else
                                                <span class="dashcart-pill pill-in" style="font-size: 0.7rem;">
                                                    <span class="pill-dot dot-green"></span> Available
                                                </span>
                                            @endif
                                        </div>
                                    @elseif($isTracked)
                                        <span class="dashcart-pill pill-grey" style="font-size: 0.7rem;">
                                            <span class="pill-dot dot-grey"></span> Not Configured
                                        </span>
                                    @else
                                        <span class="dashcart-pill pill-untracked" style="font-size: 0.7rem;">
                                            <span class="pill-dot dot-grey"></span> Untracked
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- 5. Actions Toolbar --}}
                            <td class="pe-4 py-3 text-end">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1.5">
                                    {{-- Quick Stock In Button --}}
                                    @if($canManageStock && $isTracked)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-dashcart-emerald d-inline-flex align-items-center justify-content-center"
                                            onclick="openStockInModalSingle({{ $dish->id }}, '{{ addslashes($dish->name) }}', {{ (int)($dish->prepared_stock_quantity ?? 0) }})"
                                            title="Quick Stock In (+)"
                                        >
                                            <i class="bi bi-plus-lg me-1"></i>Stock In
                                        </button>

                                        {{-- Adjust Stock Button (Wastage, Damaged, Reconciliation) --}}
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-dashcart-light d-inline-flex align-items-center justify-content-center"
                                            onclick="openAdjustModal({{ $dish->id }}, '{{ addslashes($dish->name) }}', {{ (int)($dish->prepared_stock_quantity ?? 0) }})"
                                            title="Log Wastage, Damaged or Reconciliation"
                                        >
                                            <i class="bi bi-sliders me-1"></i>Adjust
                                        </button>
                                    @endif

                                    {{-- Toggle Tracking Button --}}
                                    @if($canManageStock)
                                        <form method="POST" action="{{ route('stock.toggle-tracking', $dish) }}" class="d-inline m-0">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-dashcart-icon {{ $isTracked ? 'text-success' : 'text-secondary' }}"
                                                title="{{ $isTracked ? 'Dish is tracked. Click to disable portion tracking.' : 'Dish is untracked. Click to enable portion tracking.' }}"
                                            >
                                                <i class="bi {{ $isTracked ? 'bi-check-circle-fill' : 'bi-dash-circle' }}"></i>
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Quick Availability Modal --}}
                                    @if($canManageStock)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-dashcart-icon text-primary"
                                            onclick="openAvailabilityModal({{ $dish->id }}, '{{ addslashes($dish->name) }}', '{{ $dish->availability }}')"
                                            title="Update Availability Status"
                                        >
                                            <i class="bi bi-toggle-on"></i>
                                        </button>
                                    @endif

                                    {{-- History Modal Trigger --}}
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-dashcart-icon text-secondary"
                                        onclick="openHistoryModal({{ $dish->id }}, '{{ addslashes($dish->name) }}')"
                                        title="View Stock Transaction Ledger"
                                    >
                                        <i class="bi bi-journal-text"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-secondary">
                                <i class="bi bi-inbox fs-2 text-muted mb-2 d-block"></i>
                                <div class="fw-semibold text-dark">No dishes match the selected filters</div>
                                <span class="small text-muted">Try clearing the search query or selecting another filter.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($items->hasPages())
            <div class="p-3 px-4 bg-white border-top d-flex align-items-center justify-content-between" style="border-color: #f1f5f9 !important;">
                <div class="small text-secondary">
                    Page {{ $items->currentPage() }} of {{ $items->lastPage() }}
                </div>
                <div>
                    {{ $items->links() }}
                </div>
            </div>
        @endif
    </div>

    {{-- Shared Stock Modals --}}
    @include('stock.partials.modals')
</div>

<style>
/* Dashcart Theme CSS */
.dashcart-card {
    background-color: #ffffff;
    border: 1px solid #eef2f6 !important;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.dashcart-card:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}
.dashcart-stat-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
}
.dashcart-stat-number {
    font-size: 1.85rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1.1;
}
.dashcart-icon-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
}
.bg-sky-subtle { background-color: #e0f2fe; }
.text-sky { color: #0284c7; }
.bg-emerald-subtle { background-color: #ecfdf5; }
.text-emerald { color: #10b981; }
.bg-amber-subtle { background-color: #fffbeb; }
.text-amber { color: #f59e0b; }
.bg-rose-subtle { background-color: #fef2f2; }
.text-rose { color: #ef4444; }

.dashcart-thead {
    background-color: #f8fafc;
    color: #64748b;
    font-size: 0.72rem;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #f1f5f9;
}

.dashcart-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background-color: #ecfdf5;
    color: #047857;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    border: 1px solid #d1fae5;
    flex-shrink: 0;
}

/* Status Pills */
.dashcart-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1;
}
.pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.dot-green { background-color: #10b981; }
.dot-yellow { background-color: #f59e0b; }
.dot-red { background-color: #ef4444; }
.dot-grey { background-color: #94a3b8; }

.pill-in { background-color: #ecfdf5; color: #047857; border: 1px solid #d1fae5; }
.pill-low { background-color: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
.pill-out { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
.pill-grey { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.pill-untracked { background-color: #f8fafc; color: #64748b; border: 1px dashed #cbd5e1; }

/* Buttons */
.btn-dashcart-emerald {
    height: 30px;
    padding: 0 0.7rem;
    background-color: #10b981;
    border: 1px solid #10b981;
    color: #ffffff;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.15s ease;
}
.btn-dashcart-emerald:hover {
    background-color: #059669;
    border-color: #059669;
    color: #ffffff;
}

.btn-dashcart-light {
    height: 30px;
    padding: 0 0.65rem;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.15s ease;
}
.btn-dashcart-light:hover {
    background-color: #f1f5f9;
    color: #0f172a;
}

.btn-dashcart-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.btn-dashcart-icon:hover {
    background-color: #f8fafc;
    border-color: #cbd5e1;
}
</style>

<script>
function openStockInModalSingle(id, name, stock) {
    const form = document.getElementById('adjustStockForm');
    form.action = `/stock/${id}/adjust`;
    document.getElementById('adjustDishName').textContent = name;
    document.getElementById('adjustCurrentStockBadge').textContent = (stock ?? 0) + ' portions';
    document.getElementById('adjustQuantityInput').value = '';
    document.getElementById('adjustReasonInput').value = 'Fresh kitchen batch added for service';
    document.getElementById('adjustTypeSelect').value = 'stock_in';
    handleAdjustTypeChange('stock_in');
    new bootstrap.Modal(document.getElementById('adjustStockModal')).show();
}

function openStockInBatchModal() {
    window.location.href = "{{ route('stock.stock-in') }}";
}
</script>
@endsection
