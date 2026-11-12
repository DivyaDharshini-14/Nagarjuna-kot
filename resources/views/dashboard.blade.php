@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="dashcart-dashboard">
    {{-- Header (Dashcart Clean Style: Greeting & Subtitle Only - No Live Sync, No Refresh, No New Order) --}}
    <div class="mb-4">
        @php
            $hour = (int) date('H');
            $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        @endphp
        <h1 class="h3 fw-bold text-dark mb-1" style="letter-spacing: -0.02em; font-size: 1.75rem;">
            {{ $greeting }}, {{ auth()->user()->name }} <span class="wave-emoji">👋</span>
        </h1>
        <p class="text-secondary small mb-0" style="font-size: 0.88rem;">
            Here's what's happening at your restaurant today.
        </p>
    </div>

    {{-- Centralized Filters Panel (Date, Company, Branch, Kitchen, Order Number, Status, Filter & Reset Buttons) --}}
    <div class="card p-3 p-md-4 border-0 shadow-sm mb-4 filter-panel-card">
        <form method="GET" action="{{ route('dashboard') }}" id="dashboardFilters" class="row g-3 align-items-end">
            {{-- 1. Date Selector --}}
            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <label for="dashboard_date" class="form-label small fw-semibold text-secondary mb-1">
                    <i class="bi bi-calendar3 me-1 text-success"></i>Date
                </label>
                <input
                    type="date"
                    name="date"
                    id="dashboard_date"
                    class="form-control form-control-sm border shadow-none"
                    value="{{ $date ?? now()->toDateString() }}"
                    style="font-size: 0.85rem; height: 38px; border-radius: 10px;"
                >
            </div>

            {{-- 2. Company Selector (Super Admin only, hidden otherwise) --}}
            @if(!empty($isSuper))
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                    <label for="dashboard_company" class="form-label small fw-semibold text-secondary mb-1">
                        <i class="bi bi-buildings me-1 text-success"></i>Company
                    </label>
                    <select
                        name="company_id"
                        id="dashboard_company"
                        class="form-select form-select-sm border shadow-none"
                        style="font-size: 0.85rem; height: 38px; border-radius: 10px;"
                    >
                        <option value="">All Companies</option>
                        @foreach($companies ?? [] as $comp)
                            <option value="{{ $comp->id }}" @selected((string)($selectedCompanyId ?? '') === (string)$comp->id)>
                                {{ $comp->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="company_id" value="{{ $selectedCompanyId ?? '' }}">
            @endif

            {{-- 3. Branch Selector: Admin views all branches; Branch Manager locked to assigned branch --}}
            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <label for="dashboard_branch" class="form-label small fw-semibold text-secondary mb-1">
                    <i class="bi bi-geo-alt me-1 text-success"></i>Branch
                </label>
                @if(empty($locksBranch))
                    <select
                        name="branch_id"
                        id="dashboard_branch"
                        class="form-select form-select-sm border shadow-none"
                        style="font-size: 0.85rem; height: 38px; border-radius: 10px;"
                    >
                        <option value="">All Branches</option>
                        @foreach($branches ?? [] as $b)
                            <option value="{{ $b->id }}" @selected((string)($selectedBranchId ?? '') === (string)$b->id)>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" name="branch_id" value="{{ $selectedBranchId ?? '' }}">
                    <div class="d-flex align-items-center px-3 border bg-light text-dark fw-semibold" style="height: 38px; border-radius: 10px; font-size: 0.85rem;">
                        <i class="bi bi-lock-fill text-muted me-1.5" style="font-size: 0.8rem;"></i>
                        <span class="text-truncate">{{ $branches->firstWhere('id', $selectedBranchId)->name ?? auth()->user()->branch?->name ?? 'Assigned Branch' }}</span>
                    </div>
                @endif
            </div>

            {{-- 4. Kitchen Selector --}}
            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <label for="dashboard_kitchen" class="form-label small fw-semibold text-secondary mb-1">
                    <i class="bi bi-egg-fried me-1 text-success"></i>Kitchen
                </label>
                <select
                    name="kitchen_id"
                    id="dashboard_kitchen"
                    class="form-select form-select-sm border shadow-none"
                    style="font-size: 0.85rem; height: 38px; border-radius: 10px;"
                >
                    <option value="">All Kitchens</option>
                    @foreach($kitchens ?? [] as $k)
                        <option value="{{ $k->id }}" @selected((string)($selectedKitchenId ?? '') === (string)$k->id)>
                            {{ $k->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- 5. Order Number Search --}}
            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <label for="dashboard_order_number" class="form-label small fw-semibold text-secondary mb-1">
                    <i class="bi bi-hash me-1 text-success"></i>Order Number
                </label>
                <input
                    type="text"
                    name="order_number"
                    id="dashboard_order_number"
                    class="form-control form-control-sm border shadow-none"
                    placeholder="e.g. ORD-0001"
                    value="{{ $selectedOrderNumber ?? '' }}"
                    style="font-size: 0.85rem; height: 38px; border-radius: 10px;"
                >
            </div>

            {{-- 6. Status Selector --}}
            <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                <label for="dashboard_status" class="form-label small fw-semibold text-secondary mb-1">
                    <i class="bi bi-funnel me-1 text-success"></i>Status
                </label>
                <select
                    name="status"
                    id="dashboard_status"
                    class="form-select form-select-sm border shadow-none"
                    style="font-size: 0.85rem; height: 38px; border-radius: 10px;"
                >
                    <option value="">All Statuses</option>
                    <option value="active" @selected(($selectedStatus ?? '') === 'active')>Active</option>
                    <option value="preparing" @selected(($selectedStatus ?? '') === 'preparing')>Preparing</option>
                    <option value="ready" @selected(($selectedStatus ?? '') === 'ready')>Ready</option>
                    <option value="completed" @selected(($selectedStatus ?? '') === 'completed')>Completed</option>
                    <option value="cancelled" @selected(($selectedStatus ?? '') === 'cancelled')>Cancelled</option>
                </select>
            </div>

            {{-- Action Buttons: Filter & Reset --}}
            <div class="col-12 d-flex justify-content-end gap-2 pt-1 border-top mt-3" style="border-color: #f1f5f9 !important;">
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-light border text-secondary px-3.5 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="border-radius: 8px;">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Reset</span>
                </a>
                <button type="submit" class="btn btn-sm text-white px-4 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="background-color: #10b981; border-color: #10b981; border-radius: 8px;">
                    <i class="bi bi-funnel-fill"></i>
                    <span>Filter</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Six Original KPI Cards (Total Orders, Active Orders, Preparing, Ready, Completed, Cancelled) --}}
    <div class="row g-3 mb-4">
        {{-- 1. Total Orders --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card p-3 h-100 border-0 shadow-sm stat-dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <span class="stat-heading-label">Total Orders</span>
                        <div class="stat-icon-square icon-emerald">
                            <i class="bi bi-bag-check"></i>
                        </div>
                    </div>
                    <div class="stat-big-val text-dark" id="stat-total">
                        {{ number_format($summary['total'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2">
                    <div class="stat-trend-meta {{ ($vsYesterdayPct ?? 0) >= 0 ? 'text-success' : 'text-danger' }}" id="stat-trend-total">
                        <i class="bi {{ ($vsYesterdayPct ?? 0) >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short' }}"></i>
                        <span>{{ ($vsYesterdayPct ?? 0) >= 0 ? '+' : '' }}{{ $vsYesterdayPct ?? 0 }}% vs yest</span>
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: {{ ($vsYesterdayPct ?? 0) >= 0 ? '#10b981' : '#ef4444' }}; fill: none; stroke-width: 1.8;">
                            <path d="M0,12 Q12,6 25,10 T50,{{ ($vsYesterdayPct ?? 0) >= 0 ? '3' : '14' }}"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Active Orders --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card p-3 h-100 border-0 shadow-sm stat-dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <span class="stat-heading-label">Active Orders</span>
                        <div class="stat-icon-square icon-cyan">
                            <i class="bi bi-egg"></i>
                        </div>
                    </div>
                    <div class="stat-big-val text-dark" id="stat-active">
                        {{ number_format($summary['active'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2">
                    <div class="stat-trend-meta text-muted">
                        <span class="dot-live"></span> Live in dining
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: #0284c7; fill: none; stroke-width: 1.8;">
                            <path d="M0,10 Q15,4 30,12 T50,6"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Preparing --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card p-3 h-100 border-0 shadow-sm stat-dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <span class="stat-heading-label">Preparing</span>
                        <div class="stat-icon-square icon-amber">
                            <i class="bi bi-fire"></i>
                        </div>
                    </div>
                    <div class="stat-big-val text-dark" id="stat-preparing">
                        {{ number_format($summary['preparing'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2">
                    <div class="stat-trend-meta text-warning">
                        <i class="bi bi-clock me-1"></i>In kitchen prep
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: #f59e0b; fill: none; stroke-width: 1.8;">
                            <path d="M0,12 Q15,6 30,10 T50,4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. Ready --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card p-3 h-100 border-0 shadow-sm stat-dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <span class="stat-heading-label">Ready</span>
                        <div class="stat-icon-square icon-green">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                    </div>
                    <div class="stat-big-val text-dark" id="stat-ready">
                        {{ number_format($summary['ready'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2">
                    <div class="stat-trend-meta text-success">
                        <i class="bi bi-bell me-1"></i>Ready for pickup
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: #10b981; fill: none; stroke-width: 1.8;">
                            <path d="M0,14 Q20,10 35,4 T50,8"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 5. Completed --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card p-3 h-100 border-0 shadow-sm stat-dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <span class="stat-heading-label">Completed</span>
                        <div class="stat-icon-square icon-purple">
                            <i class="bi bi-check-all"></i>
                        </div>
                    </div>
                    <div class="stat-big-val text-dark" id="stat-completed">
                        {{ number_format($summary['completed'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2">
                    <div class="stat-trend-meta" style="color: #8b5cf6;">
                        <i class="bi bi-patch-check me-1"></i>Billed & settled
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: #8b5cf6; fill: none; stroke-width: 1.8;">
                            <path d="M0,14 Q15,12 30,6 T50,3"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- 6. Cancelled --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card p-3 h-100 border-0 shadow-sm stat-dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <span class="stat-heading-label">Cancelled</span>
                        <div class="stat-icon-square icon-rose">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                    <div class="stat-big-val text-dark" id="stat-cancelled">
                        {{ number_format($summary['cancelled'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2">
                    <div class="stat-trend-meta text-danger">
                        <i class="bi bi-dash-circle me-1"></i>Voided orders
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 44px; height: 16px; stroke: #ef4444; fill: none; stroke-width: 1.8;">
                            <path d="M0,4 Q20,8 35,12 T50,14"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Full-width Order Status Pipeline Card --}}
    <div class="card p-4 border-0 shadow-sm mb-4 status-pipeline-card">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
            <div>
                <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                    Order Status Pipeline
                </h2>
                <span class="text-secondary small" style="font-size: 0.82rem;">
                    Visual progression and live distribution across all status states
                </span>
            </div>
            <div class="text-secondary small fw-medium">
                Total Orders: <strong class="text-dark" id="status-pipeline-total">{{ number_format($summary['total'] ?? 0) }}</strong>
            </div>
        </div>

        {{-- Multi-segment Progress Pipeline Bar --}}
        <div class="status-pipeline-bar mb-3" id="statusPipelineBar">
            @php
                $distActive = $statusDistribution['active']['percentage'] ?? 0;
                $distPrep = $statusDistribution['preparing']['percentage'] ?? 0;
                $distReady = $statusDistribution['ready']['percentage'] ?? 0;
                $distComp = $statusDistribution['completed']['percentage'] ?? 0;
                $distCanc = $statusDistribution['cancelled']['percentage'] ?? 0;
            @endphp
            <div class="pipeline-segment seg-active" id="seg-active" style="width: {{ $distActive }}%;" title="Active: {{ $summary['active'] ?? 0 }} ({{ $distActive }}%)"></div>
            <div class="pipeline-segment seg-preparing" id="seg-preparing" style="width: {{ $distPrep }}%;" title="Preparing: {{ $summary['preparing'] ?? 0 }} ({{ $distPrep }}%)"></div>
            <div class="pipeline-segment seg-ready" id="seg-ready" style="width: {{ $distReady }}%;" title="Ready: {{ $summary['ready'] ?? 0 }} ({{ $distReady }}%)"></div>
            <div class="pipeline-segment seg-completed" id="seg-completed" style="width: {{ $distComp }}%;" title="Completed: {{ $summary['completed'] ?? 0 }} ({{ $distComp }}%)"></div>
            <div class="pipeline-segment seg-cancelled" id="seg-cancelled" style="width: {{ $distCanc }}%;" title="Cancelled: {{ $summary['cancelled'] ?? 0 }} ({{ $distCanc }}%)"></div>
        </div>

        {{-- Status Breakdown Chips --}}
        <div class="row g-2 pt-2">
            <div class="col-6 col-md-4 col-xl">
                <div class="status-chip d-flex align-items-center gap-2 p-2 rounded-3 border">
                    <span class="chip-dot" style="background-color: #0284c7;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Active</div>
                        <div class="fw-bold text-dark small" id="chip-active-val">
                            {{ $summary['active'] ?? 0 }} <span class="text-muted fw-normal">({{ $distActive }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="status-chip d-flex align-items-center gap-2 p-2 rounded-3 border">
                    <span class="chip-dot" style="background-color: #f59e0b;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Preparing</div>
                        <div class="fw-bold text-dark small" id="chip-prep-val">
                            {{ $summary['preparing'] ?? 0 }} <span class="text-muted fw-normal">({{ $distPrep }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="status-chip d-flex align-items-center gap-2 p-2 rounded-3 border">
                    <span class="chip-dot" style="background-color: #10b981;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Ready</div>
                        <div class="fw-bold text-dark small" id="chip-ready-val">
                            {{ $summary['ready'] ?? 0 }} <span class="text-muted fw-normal">({{ $distReady }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="status-chip d-flex align-items-center gap-2 p-2 rounded-3 border">
                    <span class="chip-dot" style="background-color: #8b5cf6;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Completed</div>
                        <div class="fw-bold text-dark small" id="chip-comp-val">
                            {{ $summary['completed'] ?? 0 }} <span class="text-muted fw-normal">({{ $distComp }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="status-chip d-flex align-items-center gap-2 p-2 rounded-3 border">
                    <span class="chip-dot" style="background-color: #ef4444;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Cancelled</div>
                        <div class="fw-bold text-dark small" id="chip-canc-val">
                            {{ $summary['cancelled'] ?? 0 }} <span class="text-muted fw-normal">({{ $distCanc }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Orders Overview (Hero Chart) & Kitchen-wise Pending Orders --}}
    <div class="row g-4 mb-4 align-items-stretch">
        {{-- Orders Overview Hero Card (Matching Dashcart Reference Orders per hour) --}}
        <div class="col-12 col-xl-7 col-xxl-8">
            <div class="card h-100 p-4 border-0 shadow-sm d-flex flex-column justify-content-between dashcart-card" style="min-height: 420px;">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                            Orders Overview
                        </h2>
                        <span class="text-secondary small fw-medium" style="font-size: 0.8rem;">
                            Hourly Volume
                        </span>
                    </div>

                    {{-- Hero Metric Highlight --}}
                    <div class="mb-3">
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="fs-1 fw-bold text-dark" id="hero-trend-total" style="letter-spacing: -0.03em; font-size: 2.75rem; line-height: 1;">
                                {{ number_format($summary['total'] ?? 0) }}
                            </span>
                        </div>
                        <div class="text-secondary small mt-1" style="font-size: 0.82rem;">
                            Today, compared to yesterday
                        </div>
                    </div>
                </div>

                {{-- Chart Canvas --}}
                <div class="position-relative flex-grow-1" style="min-height: 240px; width: 100%;">
                    <canvas id="orderTrendChart"></canvas>
                </div>

                {{-- Chart Legend --}}
                <div class="d-flex align-items-center gap-4 mt-3 pt-2 border-top text-secondary small" style="border-color: #f1f5f9 !important;">
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="legend-dot" style="background-color: #10b981;"></span>
                        <span class="fw-semibold text-dark" style="font-size: 0.8rem;">Today</span>
                    </div>
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="legend-dot" style="background-color: #a7f3d0;"></span>
                        <span class="fw-medium text-muted" style="font-size: 0.8rem;">Yesterday</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kitchen-wise Pending Orders (Matching Dashcart "Latest activity" Style) --}}
        <div class="col-12 col-xl-5 col-xxl-4">
            <div class="card h-100 p-4 border-0 shadow-sm dashcart-card d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                            Kitchen-wise Pending Orders
                        </h2>
                    </div>
                    <span class="text-secondary small fw-medium" style="font-size: 0.8rem;">
                        Live Queue
                    </span>
                </div>

                <div class="table-responsive flex-grow-1">
                    <table class="table table-borderless align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom" style="font-size: 0.72rem; letter-spacing: 0.05em; border-color: #f1f5f9 !important;">
                                <th class="ps-0 fw-semibold text-uppercase py-2.5">Kitchen</th>
                                <th class="fw-semibold text-uppercase py-2.5">Branch</th>
                                <th class="text-end pe-0 fw-semibold text-uppercase py-2.5">Pending</th>
                            </tr>
                        </thead>
                        <tbody id="kitchenBreakdownBody">
                            @forelse($kitchenBreakdown as $k)
                                @php
                                    $kName = is_array($k) ? $k['kitchen']->name : $k->kitchen->name;
                                    $bName = is_array($k) ? ($k['branch_name'] ?? $k['kitchen']->branch?->name ?? 'Main Branch') : ($k->kitchen->branch?->name ?? 'Main Branch');
                                    $kPending = is_array($k) ? $k['pending'] : $k->pending;
                                    $kInitials = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $kName), 0, 2)) ?: 'KT';
                                @endphp
                                <tr class="border-bottom-subtle">
                                    <td class="ps-0 py-2.5">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <span class="customer-avatar-pill" style="background-color: #ecfdf5; color: #059669; border-color: #d1fae5;">
                                                {{ $kInitials }}
                                            </span>
                                            <div class="fw-bold text-dark small" style="font-size: 0.88rem;">
                                                {{ $kName }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-secondary small py-2.5" style="font-size: 0.88rem;">
                                        {{ $bName }}
                                    </td>
                                    <td class="text-end pe-0 py-2.5">
                                        <span class="badge-pending-pill {{ $kPending > 0 ? 'badge-pending-active' : '' }}">
                                            {{ $kPending }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-5 small">
                                        <i class="bi bi-fire fs-3 d-block mb-2 text-secondary opacity-50"></i>
                                        No kitchens found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Orders & Branch-wise Order Count --}}
    <div class="row g-4 align-items-stretch">
        {{-- Recent Orders Table (Matching Dashcart Recent Orders Table in Reference) --}}
        <div class="col-12 col-xl-7 col-xxl-8">
            <div class="card p-4 border-0 shadow-sm dashcart-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                            Recent orders
                        </h2>
                    </div>
                    <a href="{{ route('orders.index') }}" class="text-decoration-none fw-semibold small" style="color: #10b981; font-size: 0.85rem;">
                        View all
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom" style="font-size: 0.72rem; letter-spacing: 0.05em; border-color: #f1f5f9 !important;">
                                <th class="ps-0 fw-semibold text-uppercase py-2.5">Order</th>
                                <th class="fw-semibold text-uppercase py-2.5">Branch</th>
                                <th class="fw-semibold text-uppercase py-2.5">Customer / Table</th>
                                <th class="text-center fw-semibold text-uppercase py-2.5">Status</th>
                                <th class="text-end pe-0 fw-semibold text-uppercase py-2.5">Time</th>
                            </tr>
                        </thead>
                        <tbody id="recentOrdersBody">
                            @forelse($recentOrders as $ord)
                                @php
                                    $ordNum = $ord->order_number ?? 'ORD';
                                    $tableName = $ord->table?->name ?? ($ord->table_name ?? 'Table');
                                    $branchName = $ord->branch?->name ?? ($ord->branch_name ?? 'Branch');
                                    $waiterName = $ord->waiter?->name ?? 'Staff';
                                    $status = strtolower($ord->status ?? 'active');
                                    $time = isset($ord->created_at) ? $ord->created_at->format('h:i A') : '';
                                    $initials = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tableName), 0, 2)) ?: 'T1';
                                @endphp
                                <tr class="border-bottom-subtle">
                                    <td class="ps-0 fw-bold text-dark small py-3" style="font-size: 0.88rem;">
                                        {{ $ordNum }}
                                    </td>
                                    <td class="text-secondary small py-3" style="font-size: 0.88rem;">
                                        {{ $branchName }}
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <span class="customer-avatar-pill">
                                                {{ $initials }}
                                            </span>
                                            <div>
                                                <div class="fw-medium text-dark small" style="font-size: 0.88rem; line-height: 1.2;">{{ $tableName }}</div>
                                                <div class="text-muted" style="font-size: 0.75rem;">{{ $waiterName }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center py-3">
                                        @switch($status)
                                            @case('preparing')
                                                <span class="dashcart-pill pill-amber"><span class="pill-dot bg-amber"></span> Preparing</span>
                                                @break
                                            @case('ready')
                                                <span class="dashcart-pill pill-green"><span class="pill-dot bg-green"></span> Ready</span>
                                                @break
                                            @case('completed')
                                                <span class="dashcart-pill pill-purple"><span class="pill-dot bg-purple"></span> Completed</span>
                                                @break
                                            @case('cancelled')
                                                <span class="dashcart-pill pill-red"><span class="pill-dot bg-red"></span> Cancelled</span>
                                                @break
                                            @default
                                                <span class="dashcart-pill pill-blue"><span class="pill-dot bg-blue"></span> Active</span>
                                        @endswitch
                                    </td>
                                    <td class="text-end pe-0 text-muted small py-3" style="font-size: 0.85rem;">
                                        {{ $time }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5 small">
                                        <i class="bi bi-receipt fs-3 d-block mb-2 text-secondary opacity-50"></i>
                                        No orders recorded for this selection yet
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Branch-wise Order Count (Horizontal Green Progress Bars with Scale Axis) --}}
        <div class="col-12 col-xl-5 col-xxl-4">
            <div class="card h-100 p-4 border-0 shadow-sm dashcart-card d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                            Branch-wise Order Count
                        </h2>
                    </div>
                    <span class="text-secondary small fw-medium" style="font-size: 0.8rem;">
                        Distribution
                    </span>
                </div>

                <div class="branch-chart-container py-1 flex-grow-1" id="branchBreakdownWrapper">
                    <div id="branchBreakdownRows">
                        @php
                            $maxVal = max(1, (int)($branchBreakdown->max('total') ?? 1));
                        @endphp

                        @forelse($branchBreakdown as $index => $b)
                            @php
                                $bName = is_array($b) ? $b['branch']->name : $b->branch->name;
                                $bTotal = is_array($b) ? $b['total'] : $b->total;
                                $barWidthPct = $bTotal > 0 ? min(100, max(6, round(($bTotal / $maxVal) * 100))) : 0;
                                
                                $barGradient = match($index % 3) {
                                    0 => 'linear-gradient(90deg, #10b981, #059669)',
                                    1 => 'linear-gradient(90deg, #34d399, #10b981)',
                                    default => 'linear-gradient(90deg, #6ee7b7, #34d399)',
                                };
                            @endphp
                            <div class="branch-bar-row mb-3">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="branch-name-label text-truncate" title="{{ $bName }}">
                                        {{ $bName }}
                                    </div>
                                    <div class="branch-bar-track flex-grow-1 position-relative">
                                        <div class="branch-bar-fill" style="width: {{ $barWidthPct }}%; background: {{ $barGradient }}; transition: width 0.6s ease;"></div>
                                    </div>
                                    <div class="branch-count-badge">
                                        {{ $bTotal }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-5 small">
                                <i class="bi bi-building fs-3 d-block mb-2 text-secondary opacity-50"></i>
                                No branches found for current filters
                            </div>
                        @endforelse
                    </div>

                    {{-- Horizontal Scale Axis --}}
                    <div class="branch-scale-axis d-flex justify-content-between pt-2.5 border-top text-muted small mt-3" style="border-color: #f1f5f9 !important; font-size: 0.75rem;">
                        <span>0</span>
                        <span>20</span>
                        <span>40</span>
                        <span>60</span>
                        <span>80+</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashcart Dashboard Theme */
.dashcart-dashboard {
    color: #0f172a;
}

/* Dashcart Clean Card Style */
.dashcart-card,
.filter-panel-card,
.stat-dashcart-card,
.status-pipeline-card {
    background: #ffffff;
    border: 1px solid #eef2f6 !important;
    border-radius: 16px;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
}

.stat-dashcart-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-dashcart-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.08) !important;
}

/* Stat Card Square Icons */
.stat-icon-square {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.icon-emerald { background: #ecfdf5; color: #10b981; }
.icon-cyan { background: #e0f2fe; color: #0284c7; }
.icon-amber { background: #fffbeb; color: #d97706; }
.icon-green { background: #f0fdf4; color: #16a34a; }
.icon-purple { background: #f5f3ff; color: #7c3aed; }
.icon-rose { background: #fef2f2; color: #dc2626; }

.stat-heading-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #64748b;
    letter-spacing: -0.01em;
}

.stat-big-val {
    font-size: 1.65rem;
    font-weight: 700;
    line-height: 1.1;
    margin-bottom: 0.25rem;
}

.stat-trend-meta {
    font-size: 0.74rem;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.dot-live {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #0284c7;
    display: inline-block;
    margin-right: 5px;
}

/* Full-width Status Pipeline */
.status-pipeline-bar {
    height: 14px;
    background-color: #f1f5f9;
    border-radius: 9999px;
    overflow: hidden;
    display: flex;
    width: 100%;
}
.pipeline-segment {
    height: 100%;
    transition: width 0.6s ease;
}
.seg-active { background-color: #0284c7; }
.seg-preparing { background-color: #f59e0b; }
.seg-ready { background-color: #10b981; }
.seg-completed { background-color: #8b5cf6; }
.seg-cancelled { background-color: #ef4444; }

.chip-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.status-chip {
    background-color: #f8fafc;
    border-color: #eef2f6 !important;
}

/* Horizontal Bars for Branch-wise breakdown */
.branch-bar-row {
    margin-bottom: 0.85rem;
}
.branch-name-label {
    width: 110px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.branch-bar-track {
    height: 16px;
    background-color: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 6px;
    overflow: hidden;
}
.branch-bar-fill {
    height: 100%;
    border-radius: 5px;
}
.branch-count-badge {
    width: 32px;
    text-align: right;
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
}

/* Kitchen pending badge */
.badge-pending-pill {
    display: inline-block;
    min-width: 24px;
    padding: 0.2rem 0.55rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    background-color: #f1f5f9;
}
.badge-pending-pill.badge-pending-active {
    color: #dc2626;
    background-color: #fee2e2;
}

/* Customer Avatar Pill */
.customer-avatar-pill {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background-color: #ecfdf5;
    color: #059669;
    font-size: 0.72rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid #d1fae5;
}

/* Status Pill with dot matching reference */
.dashcart-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}
.pill-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.pill-green { background-color: #ecfdf5; color: #059669; }
.bg-green { background-color: #10b981; }

.pill-amber { background-color: #fffbeb; color: #d97706; }
.bg-amber { background-color: #f59e0b; }

.pill-blue { background-color: #eff6ff; color: #2563eb; }
.bg-blue { background-color: #3b82f6; }

.pill-purple { background-color: #f5f3ff; color: #7c3aed; }
.bg-purple { background-color: #8b5cf6; }

.pill-red { background-color: #fef2f2; color: #dc2626; }
.bg-red { background-color: #ef4444; }

.border-bottom-subtle {
    border-bottom: 1px solid #f8fafc;
}

/* Legend dots */
.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
</style>

@php
    $hourlyLabelsJson = json_encode($hourlyLabels ?? ['10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM']);
    $hourlyTrendJson = json_encode($hourlyTrend ?? [0, 0, 0, 0, 0, 0, 0, 0]);
    $yesterdayHourlyTrendJson = json_encode($yesterdayHourlyTrend ?? [0, 0, 0, 0, 0, 0, 0, 0]);
@endphp

@push('scripts')
<script>
let orderChart = null;
let isRefreshing = false;
let pollingInterval = null;

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function updateCounter(elementId, targetValue) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const currentVal = parseInt(el.textContent.replace(/,/g, ''), 10) || 0;
    const endVal = parseInt(targetValue, 10) || 0;
    
    if (currentVal === endVal) {
        el.textContent = endVal.toLocaleString();
        return;
    }

    el.style.transition = 'transform 0.15s ease';
    el.style.transform = 'scale(1.08)';
    el.textContent = endVal.toLocaleString();

    setTimeout(() => {
        el.style.transform = 'scale(1)';
    }, 180);
}

function getStatusPillHtml(status) {
    const s = String(status || 'active').toLowerCase();
    switch (s) {
        case 'preparing':
            return '<span class="dashcart-pill pill-amber"><span class="pill-dot bg-amber"></span> Preparing</span>';
        case 'ready':
            return '<span class="dashcart-pill pill-green"><span class="pill-dot bg-green"></span> Ready</span>';
        case 'completed':
            return '<span class="dashcart-pill pill-purple"><span class="pill-dot bg-purple"></span> Completed</span>';
        case 'cancelled':
            return '<span class="dashcart-pill pill-red"><span class="pill-dot bg-red"></span> Cancelled</span>';
        default:
            return '<span class="dashcart-pill pill-blue"><span class="pill-dot bg-blue"></span> Active</span>';
    }
}

async function fetchDashboardData() {
    if (isRefreshing) return;
    isRefreshing = true;

    try {
        const form = document.getElementById('dashboardFilters');
        const params = new URLSearchParams(new FormData(form));
        params.set('format', 'json');

        const response = await fetch(`${form.getAttribute('action') || window.location.pathname}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        });

        if (!response.ok) return;

        const data = await response.json();
        if (data && data.success) {
            // 1. Update 6 Summary Cards
            if (data.summary) {
                updateCounter('stat-total', data.summary.total);
                updateCounter('stat-active', data.summary.active);
                updateCounter('stat-preparing', data.summary.preparing);
                updateCounter('stat-ready', data.summary.ready);
                updateCounter('stat-completed', data.summary.completed);
                updateCounter('stat-cancelled', data.summary.cancelled);

                const heroTotalEl = document.getElementById('hero-trend-total');
                if (heroTotalEl) {
                    heroTotalEl.textContent = (data.summary.total || 0).toLocaleString();
                }

                const pipeTotalEl = document.getElementById('status-pipeline-total');
                if (pipeTotalEl) {
                    pipeTotalEl.textContent = (data.summary.total || 0).toLocaleString();
                }
            }

            // Update Trend vs Yesterday
            const trendEl = document.getElementById('stat-trend-total');
            if (trendEl && typeof data.vsYesterdayPct !== 'undefined') {
                const pct = data.vsYesterdayPct;
                const isPos = pct >= 0;
                trendEl.className = `stat-trend-meta ${isPos ? 'text-success' : 'text-danger'}`;
                trendEl.innerHTML = `<i class="bi ${isPos ? 'bi-arrow-up-short' : 'bi-arrow-down-short'}"></i><span>${isPos ? '+' : ''}${pct}% vs yest</span>`;
            }

            // 2. Update Full-width Status Pipeline
            if (data.statusDistribution) {
                const dist = data.statusDistribution;
                const setSegment = (id, pct, count, label) => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.style.width = `${pct}%`;
                        el.title = `${label}: ${count} (${pct}%)`;
                    }
                };
                if (dist.active) {
                    setSegment('seg-active', dist.active.percentage, dist.active.count, 'Active');
                    const chip = document.getElementById('chip-active-val');
                    if (chip) chip.innerHTML = `${dist.active.count} <span class="text-muted fw-normal">(${dist.active.percentage}%)</span>`;
                }
                if (dist.preparing) {
                    setSegment('seg-preparing', dist.preparing.percentage, dist.preparing.count, 'Preparing');
                    const chip = document.getElementById('chip-prep-val');
                    if (chip) chip.innerHTML = `${dist.preparing.count} <span class="text-muted fw-normal">(${dist.preparing.percentage}%)</span>`;
                }
                if (dist.ready) {
                    setSegment('seg-ready', dist.ready.percentage, dist.ready.count, 'Ready');
                    const chip = document.getElementById('chip-ready-val');
                    if (chip) chip.innerHTML = `${dist.ready.count} <span class="text-muted fw-normal">(${dist.ready.percentage}%)</span>`;
                }
                if (dist.completed) {
                    setSegment('seg-completed', dist.completed.percentage, dist.completed.count, 'Completed');
                    const chip = document.getElementById('chip-comp-val');
                    if (chip) chip.innerHTML = `${dist.completed.count} <span class="text-muted fw-normal">(${dist.completed.percentage}%)</span>`;
                }
                if (dist.cancelled) {
                    setSegment('seg-cancelled', dist.cancelled.percentage, dist.cancelled.count, 'Cancelled');
                    const chip = document.getElementById('chip-canc-val');
                    if (chip) chip.innerHTML = `${dist.cancelled.count} <span class="text-muted fw-normal">(${dist.cancelled.percentage}%)</span>`;
                }
            }

            // 3. Update Branch-wise Breakdown Bars
            const branchRows = document.getElementById('branchBreakdownRows');
            if (branchRows && data.branchBreakdown) {
                if (data.branchBreakdown.length === 0) {
                    branchRows.innerHTML = `
                        <div class="text-center text-muted py-4 small">
                            <i class="bi bi-building fs-4 d-block mb-1 text-secondary opacity-50"></i>
                            No branches found for current filters
                        </div>`;
                } else {
                    const maxVal = Math.max(1, ...data.branchBreakdown.map(b => b.total || 0));
                    const gradients = [
                        'linear-gradient(90deg, #10b981, #059669)',
                        'linear-gradient(90deg, #34d399, #10b981)',
                        'linear-gradient(90deg, #6ee7b7, #34d399)'
                    ];
                    let rowsHtml = '';
                    data.branchBreakdown.forEach((b, idx) => {
                        const bTotal = b.total || 0;
                        const barWidthPct = bTotal > 0 ? Math.min(100, Math.max(6, Math.round((bTotal / maxVal) * 100))) : 0;
                        const grad = gradients[idx % 3];
                        rowsHtml += `
                            <div class="branch-bar-row mb-3">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="branch-name-label text-truncate" title="${escapeHtml(b.name)}">
                                        ${escapeHtml(b.name)}
                                    </div>
                                    <div class="branch-bar-track flex-grow-1 position-relative">
                                        <div class="branch-bar-fill" style="width: ${barWidthPct}%; background: ${grad}; transition: width 0.6s ease;"></div>
                                    </div>
                                    <div class="branch-count-badge">
                                        ${bTotal}
                                    </div>
                                </div>
                            </div>`;
                    });
                    branchRows.innerHTML = rowsHtml;
                }
            }

            // 4. Update Kitchen-wise Pending Orders Table
            const kitchenBody = document.getElementById('kitchenBreakdownBody');
            if (kitchenBody && data.kitchenBreakdown) {
                if (data.kitchenBreakdown.length === 0) {
                    kitchenBody.innerHTML = `
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4 small">
                                <i class="bi bi-fire fs-4 d-block mb-1 text-secondary opacity-50"></i>
                                No kitchens found
                            </td>
                        </tr>`;
                } else {
                    let kHtml = '';
                    data.kitchenBreakdown.forEach(k => {
                        const isActive = (k.pending || 0) > 0;
                        const kInit = (k.name || 'KT').replace(/[^a-zA-Z0-9]/g, '').substring(0, 2).toUpperCase() || 'KT';
                        kHtml += `
                            <tr class="border-bottom-subtle">
                                <td class="ps-0 py-2.5">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <span class="customer-avatar-pill" style="background-color: #ecfdf5; color: #059669; border-color: #d1fae5;">
                                            ${escapeHtml(kInit)}
                                        </span>
                                        <div class="fw-bold text-dark small" style="font-size: 0.88rem;">
                                            ${escapeHtml(k.name)}
                                        </div>
                                    </div>
                                </td>
                                <td class="text-secondary small py-2.5" style="font-size: 0.88rem;">${escapeHtml(k.branch_name)}</td>
                                <td class="text-end pe-0 py-2.5">
                                    <span class="badge-pending-pill ${isActive ? 'badge-pending-active' : ''}">${k.pending}</span>
                                </td>
                            </tr>`;
                    });
                    kitchenBody.innerHTML = kHtml;
                }
            }

            // 5. Update Today's Order Trend Line Chart (Dashcart Emerald Theme)
            if (orderChart && data.hourlyTrend) {
                if (data.hourlyLabels) {
                    orderChart.data.labels = data.hourlyLabels;
                }
                orderChart.data.datasets[0].data = data.hourlyTrend;
                if (data.yesterdayHourlyTrend && orderChart.data.datasets[1]) {
                    orderChart.data.datasets[1].data = data.yesterdayHourlyTrend;
                }
                orderChart.update('none');
            }

            // 6. Update Recent Orders Table
            const recentOrdersBody = document.getElementById('recentOrdersBody');
            if (recentOrdersBody && data.recentOrders) {
                if (data.recentOrders.length === 0) {
                    recentOrdersBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4 small">
                                <i class="bi bi-receipt fs-4 d-block mb-1 text-secondary opacity-50"></i>
                                No orders recorded for this selection yet
                            </td>
                        </tr>`;
                } else {
                    let oHtml = '';
                    data.recentOrders.forEach(ord => {
                        const statusPill = getStatusPillHtml(ord.status);
                        const initials = (ord.table_name || 'T1').replace(/[^a-zA-Z0-9]/g, '').substring(0, 2).toUpperCase() || 'T1';
                        oHtml += `
                            <tr class="border-bottom-subtle">
                                <td class="ps-0 fw-bold text-dark small py-2.5">${escapeHtml(ord.order_number)}</td>
                                <td class="text-secondary small py-2.5">${escapeHtml(ord.branch_name)}</td>
                                <td class="py-2.5">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="customer-avatar-pill">${escapeHtml(initials)}</span>
                                        <div>
                                            <div class="fw-medium text-dark small" style="line-height: 1.2;">${escapeHtml(ord.table_name)}</div>
                                            <div class="text-muted" style="font-size: 0.72rem;">Dining</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center py-2.5">${statusPill}</td>
                                <td class="text-end pe-0 text-muted small py-2.5">${escapeHtml(ord.time)}</td>
                            </tr>`;
                    });
                    recentOrdersBody.innerHTML = oHtml;
                }
            }
        }
    } catch (err) {
        console.warn('Dashboard live sync note:', err);
    } finally {
        isRefreshing = false;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // 1. Initialize Today's Order Trend Line Chart (Dashcart Emerald Gradient)
    const ctx = document.getElementById('orderTrendChart');
    if (ctx) {
        const labels = {!! $hourlyLabelsJson !!};
        const todayData = {!! $hourlyTrendJson !!};
        const yesterdayData = {!! $yesterdayHourlyTrendJson !!};

        const chartContext = ctx.getContext('2d');
        const gradient = chartContext.createLinearGradient(0, 0, 0, 220);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.28)');
        gradient.addColorStop(0.7, 'rgba(16, 185, 129, 0.05)');
        gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

        orderChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Today',
                        data: todayData,
                        borderColor: '#10b981',
                        borderWidth: 2.5,
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.42,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Yesterday',
                        data: yesterdayData,
                        borderColor: '#a7f3d0',
                        borderWidth: 1.8,
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.42,
                        pointRadius: 0,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0c1e17',
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 8,
                        cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                return ` ${context.dataset.label}: ${context.parsed.y} orders`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" }
                        }
                    },
                    y: {
                        min: 0,
                        suggestedMax: 10,
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false,
                        },
                        ticks: {
                            stepSize: 5,
                            color: '#94a3b8',
                            font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" }
                        }
                    }
                }
            }
        });
    }

    // 2. Set up background polling every 8 seconds (clean, invisible sync)
    pollingInterval = setInterval(() => {
        if (!document.hidden) {
            fetchDashboardData();
        }
    }, 8000);

    // Immediate sync when tab regains focus
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            fetchDashboardData();
        }
    });
});
</script>
@endpush
@endsection
