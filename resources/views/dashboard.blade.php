@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="tabletop-dashboard py-2">
    {{-- Breadcrumb / Super-title --}}
    <div class="text-secondary small mb-1" style="font-size: 0.82rem; color: #6b7280;">
        Dashboard / Overview
    </div>

    {{-- Top Header: Greeting, Subtitle & Branch Badge --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            @php
                $hour = (int) date('H');
                $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
            @endphp
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.75rem; letter-spacing: -0.02em;">
                {{ $greeting }}, {{ auth()->user()->name }} <span class="wave-emoji">👋</span>
            </h1>
            <p class="text-secondary small mb-0" style="font-size: 0.86rem; color: #6b7280;">
                Here's what's happening at your restaurant today.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if(!empty($locksBranch))
                <span class="badge bg-light text-secondary border px-3 py-1.5 fw-semibold" style="font-size: 0.75rem; border-radius: 6px;">
                    <i class="bi bi-geo-alt me-1 text-danger"></i>{{ auth()->user()->branch?->name ?? 'Assigned Branch' }}
                </span>
            @else
                <span class="badge bg-light text-secondary border px-3 py-1.5 fw-semibold" style="font-size: 0.75rem; border-radius: 6px;">
                    <i class="bi bi-buildings me-1 text-danger"></i>All Branches
                </span>
            @endif
        </div>
    </div>

    {{-- Hidden form for background real-time sync (No UI filter controls) --}}
    <form id="dashboardFilters" method="GET" action="{{ route('dashboard') }}" class="d-none">
        <input type="hidden" name="format" value="json">
    </form>

    {{-- 1. Six Tabletop KPI Summary Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Orders --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tabletop-card tabletop-kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="kpi-label">Total Orders</span>
                        <div class="kpi-icon-box bg-emerald-subtle text-emerald">
                            <i class="bi bi-bag-check"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-dark" id="stat-total">
                        {{ number_format($summary['total'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: #f3f4f6 !important;">
                    <div class="kpi-meta {{ ($vsYesterdayPct ?? 0) >= 0 ? 'text-success' : 'text-danger' }}" id="stat-trend-total">
                        <i class="bi {{ ($vsYesterdayPct ?? 0) >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short' }}"></i>
                        <span>{{ ($vsYesterdayPct ?? 0) >= 0 ? '+' : '' }}{{ $vsYesterdayPct ?? 0 }}% vs yest</span>
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 40px; height: 14px; stroke: {{ ($vsYesterdayPct ?? 0) >= 0 ? '#10b981' : '#ef4444' }}; fill: none; stroke-width: 1.8;">
                            <path d="M0,12 Q12,6 25,10 T50,{{ ($vsYesterdayPct ?? 0) >= 0 ? '3' : '14' }}"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Active Orders --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tabletop-card tabletop-kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="kpi-label">Active Orders</span>
                        <div class="kpi-icon-box bg-blue-subtle text-primary">
                            <i class="bi bi-egg"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-dark" id="stat-active">
                        {{ number_format($summary['active'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: #f3f4f6 !important;">
                    <div class="kpi-meta text-muted">
                        <span class="dot-live"></span> Live dining
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 40px; height: 14px; stroke: #0284c7; fill: none; stroke-width: 1.8;">
                            <path d="M0,10 Q15,4 30,12 T50,6"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Preparing --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tabletop-card tabletop-kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="kpi-label">Preparing</span>
                        <div class="kpi-icon-box bg-amber-subtle text-amber">
                            <i class="bi bi-fire"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-dark" id="stat-preparing">
                        {{ number_format($summary['preparing'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: #f3f4f6 !important;">
                    <div class="kpi-meta text-warning">
                        <i class="bi bi-clock me-1"></i>In kitchen
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 40px; height: 14px; stroke: #f59e0b; fill: none; stroke-width: 1.8;">
                            <path d="M0,12 Q15,6 30,10 T50,4"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ready --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tabletop-card tabletop-kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="kpi-label">Ready</span>
                        <div class="kpi-icon-box bg-green-subtle text-success">
                            <i class="bi bi-bell"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-dark" id="stat-ready">
                        {{ number_format($summary['ready'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: #f3f4f6 !important;">
                    <div class="kpi-meta text-success">
                        <i class="bi bi-check2 me-1"></i>Pickup ready
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 40px; height: 14px; stroke: #10b981; fill: none; stroke-width: 1.8;">
                            <path d="M0,14 Q20,10 35,4 T50,8"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Completed --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tabletop-card tabletop-kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="kpi-label">Completed</span>
                        <div class="kpi-icon-box bg-purple-subtle text-purple">
                            <i class="bi bi-check-all"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-dark" id="stat-completed">
                        {{ number_format($summary['completed'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: #f3f4f6 !important;">
                    <div class="kpi-meta text-purple">
                        <i class="bi bi-patch-check me-1"></i>Billed &amp; settled
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 40px; height: 14px; stroke: #8b5cf6; fill: none; stroke-width: 1.8;">
                            <path d="M0,14 Q15,12 30,6 T50,3"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cancelled --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tabletop-card tabletop-kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="kpi-label">Cancelled</span>
                        <div class="kpi-icon-box bg-rose-subtle text-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-dark" id="stat-cancelled">
                        {{ number_format($summary['cancelled'] ?? 0) }}
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: #f3f4f6 !important;">
                    <div class="kpi-meta text-danger">
                        <i class="bi bi-dash-circle me-1"></i>Voided
                    </div>
                    <div class="mini-sparkline">
                        <svg viewBox="0 0 50 16" style="width: 40px; height: 14px; stroke: #ef4444; fill: none; stroke-width: 1.8;">
                            <path d="M0,4 Q20,8 35,12 T50,14"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Order Status Pipeline Card --}}
    <div class="tabletop-card p-3.5 mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
            <div>
                <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1rem; letter-spacing: -0.01em;">
                    Order Status Pipeline
                </h2>
                <span class="text-secondary small" style="font-size: 0.8rem; color: #6b7280;">
                    Live distribution across all order workflow stages
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
        <div class="row g-2 pt-1">
            <div class="col-6 col-md-4 col-xl">
                <div class="tabletop-status-chip d-flex align-items-center gap-2 p-2">
                    <span class="chip-dot" style="background-color: #0284c7;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Active</div>
                        <div class="fw-bold text-dark small" id="chip-active-val">
                            {{ $summary['active'] ?? 0 }} <span class="text-muted fw-normal" style="font-size: 0.7rem;">({{ $distActive }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="tabletop-status-chip d-flex align-items-center gap-2 p-2">
                    <span class="chip-dot" style="background-color: #f59e0b;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Preparing</div>
                        <div class="fw-bold text-dark small" id="chip-prep-val">
                            {{ $summary['preparing'] ?? 0 }} <span class="text-muted fw-normal" style="font-size: 0.7rem;">({{ $distPrep }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="tabletop-status-chip d-flex align-items-center gap-2 p-2">
                    <span class="chip-dot" style="background-color: #10b981;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Ready</div>
                        <div class="fw-bold text-dark small" id="chip-ready-val">
                            {{ $summary['ready'] ?? 0 }} <span class="text-muted fw-normal" style="font-size: 0.7rem;">({{ $distReady }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="tabletop-status-chip d-flex align-items-center gap-2 p-2">
                    <span class="chip-dot" style="background-color: #8b5cf6;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Completed</div>
                        <div class="fw-bold text-dark small" id="chip-comp-val">
                            {{ $summary['completed'] ?? 0 }} <span class="text-muted fw-normal" style="font-size: 0.7rem;">({{ $distComp }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="tabletop-status-chip d-flex align-items-center gap-2 p-2">
                    <span class="chip-dot" style="background-color: #ef4444;"></span>
                    <div class="min-w-0">
                        <div class="text-secondary small fw-semibold" style="font-size: 0.72rem;">Cancelled</div>
                        <div class="fw-bold text-dark small" id="chip-canc-val">
                            {{ $summary['cancelled'] ?? 0 }} <span class="text-muted fw-normal" style="font-size: 0.7rem;">({{ $distCanc }}%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Middle Row: Orders Overview Chart & Kitchen-wise Pending Orders --}}
    <div class="row g-4 mb-4 align-items-stretch">
        {{-- Orders Overview Chart --}}
        <div class="col-12 col-xl-7 col-xxl-8">
            <div class="tabletop-card p-4 h-100 d-flex flex-column justify-content-between" style="min-height: 400px;">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                                Orders Overview
                            </h2>
                            <span class="text-secondary small" style="font-size: 0.8rem; color: #6b7280;">
                                Hourly order trends (Today vs Yesterday)
                            </span>
                        </div>
                        <span class="badge bg-light text-secondary border px-2.5 py-1 small fw-medium" style="font-size: 0.75rem;">
                            Hourly
                        </span>
                    </div>

                    {{-- Metric Highlight --}}
                    <div class="mb-3">
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="fs-1 fw-bold text-dark" id="hero-trend-total" style="letter-spacing: -0.03em; font-size: 2.5rem; line-height: 1;">
                                {{ number_format($summary['total'] ?? 0) }}
                            </span>
                        </div>
                        <div class="text-secondary small mt-1" style="font-size: 0.8rem; color: #6b7280;">
                            Total volume registered today
                        </div>
                    </div>
                </div>

                {{-- Chart Canvas --}}
                <div class="position-relative flex-grow-1" style="min-height: 220px; width: 100%;">
                    <canvas id="orderTrendChart"></canvas>
                </div>

                {{-- Chart Legend --}}
                <div class="d-flex align-items-center gap-4 mt-3 pt-2.5 border-top text-secondary small" style="border-color: #f3f4f6 !important;">
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

        {{-- Kitchen-wise Pending Orders Table --}}
        <div class="col-12 col-xl-5 col-xxl-4">
            <div class="tabletop-card p-4 h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom" style="border-color: #f3f4f6 !important;">
                    <div>
                        <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                            Kitchen Queue
                        </h2>
                        <span class="text-secondary small" style="font-size: 0.8rem; color: #6b7280;">
                            Active prep tickets
                        </span>
                    </div>
                    <span class="badge bg-light text-secondary border px-2 py-1 small fw-medium" style="font-size: 0.72rem;">
                        Live
                    </span>
                </div>

                <div class="table-responsive flex-grow-1">
                    <table class="table table-hover align-middle mb-0 tabletop-table">
                        <thead>
                            <tr>
                                <th class="ps-0 py-2">Kitchen</th>
                                <th class="py-2">Branch</th>
                                <th class="text-end pe-0 py-2">Pending</th>
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
                                <tr>
                                    <td class="ps-0 py-2.5">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="tabletop-avatar">
                                                {{ $kInitials }}
                                            </span>
                                            <span class="fw-medium text-dark small" style="font-size: 0.86rem;">
                                                {{ $kName }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-secondary small py-2.5" style="font-size: 0.84rem;">
                                        {{ $bName }}
                                    </td>
                                    <td class="text-end pe-0 py-2.5">
                                        <span class="tabletop-pending-badge {{ $kPending > 0 ? 'badge-active' : '' }}">
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

    {{-- 4. Bottom Row: Recent Orders & Branch-wise Order Count --}}
    <div class="row g-4 align-items-stretch">
        {{-- Recent Orders Table --}}
        <div class="col-12 col-xl-7 col-xxl-8">
            <div class="tabletop-card p-4 h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom" style="border-color: #f3f4f6 !important;">
                    <div>
                        <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                            Recent Orders
                        </h2>
                        <span class="text-secondary small" style="font-size: 0.8rem; color: #6b7280;">
                            Latest transactions processed
                        </span>
                    </div>
                    <a href="{{ route('orders.index') }}" class="tabletop-link small">
                        View all <i class="bi bi-arrow-right ms-0.5"></i>
                    </a>
                </div>

                <div class="table-responsive flex-grow-1">
                    <table class="table table-hover align-middle mb-0 tabletop-table">
                        <thead>
                            <tr>
                                <th class="ps-0 py-2">Order</th>
                                <th class="py-2">Branch</th>
                                <th class="py-2">Table / Server</th>
                                <th class="text-center py-2">Status</th>
                                <th class="text-end pe-0 py-2">Time</th>
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
                                <tr>
                                    <td class="ps-0 fw-semibold text-dark small py-2.5" style="font-size: 0.86rem;">
                                        {{ $ordNum }}
                                    </td>
                                    <td class="text-secondary small py-2.5" style="font-size: 0.84rem;">
                                        {{ $branchName }}
                                    </td>
                                    <td class="py-2.5">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="tabletop-avatar">
                                                {{ $initials }}
                                            </span>
                                            <div>
                                                <div class="fw-medium text-dark small" style="font-size: 0.86rem; line-height: 1.2;">{{ $tableName }}</div>
                                                <div class="text-muted" style="font-size: 0.72rem;">{{ $waiterName }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center py-2.5">
                                        @switch($status)
                                            @case('preparing')
                                                <span class="tabletop-badge badge-amber"><span class="badge-dot dot-amber"></span> Preparing</span>
                                                @break
                                            @case('ready')
                                                <span class="tabletop-badge badge-green"><span class="badge-dot dot-green"></span> Ready</span>
                                                @break
                                            @case('completed')
                                                <span class="tabletop-badge badge-purple"><span class="badge-dot dot-purple"></span> Completed</span>
                                                @break
                                            @case('cancelled')
                                                <span class="tabletop-badge badge-red"><span class="badge-dot dot-red"></span> Cancelled</span>
                                                @break
                                            @default
                                                <span class="tabletop-badge badge-blue"><span class="badge-dot dot-blue"></span> Active</span>
                                        @endswitch
                                    </td>
                                    <td class="text-end pe-0 text-muted small py-2.5" style="font-size: 0.82rem;">
                                        {{ $time }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5 small">
                                        <i class="bi bi-receipt fs-3 d-block mb-2 text-secondary opacity-50"></i>
                                        No recent orders found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Branch-wise Order Count --}}
        <div class="col-12 col-xl-5 col-xxl-4">
            <div class="tabletop-card p-4 h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom" style="border-color: #f3f4f6 !important;">
                    <div>
                        <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem; letter-spacing: -0.01em;">
                            Branch Distribution
                        </h2>
                        <span class="text-secondary small" style="font-size: 0.8rem; color: #6b7280;">
                            Order volume by location
                        </span>
                    </div>
                    <span class="badge bg-light text-secondary border px-2 py-1 small fw-medium" style="font-size: 0.72rem;">
                        Branches
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
                            @endphp
                            <div class="branch-bar-row mb-3">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="branch-name-label text-truncate" title="{{ $bName }}">
                                        {{ $bName }}
                                    </div>
                                    <div class="branch-bar-track flex-grow-1 position-relative">
                                        <div class="branch-bar-fill" style="width: {{ $barWidthPct }}%;"></div>
                                    </div>
                                    <div class="branch-count-badge">
                                        {{ $bTotal }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-5 small">
                                <i class="bi bi-building fs-3 d-block mb-2 text-secondary opacity-50"></i>
                                No branch data found
                            </div>
                        @endforelse
                    </div>

                    {{-- Horizontal Scale Axis --}}
                    <div class="branch-scale-axis d-flex justify-content-between pt-2.5 border-top text-muted small mt-3" style="border-color: #f1f5f9 !important; font-size: 0.72rem;">
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
/* Tabletop Dashboard Theme */
.tabletop-dashboard {
    color: #111827;
}

/* Tabletop Card Container */
.tabletop-card {
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.tabletop-kpi-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.tabletop-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

/* KPI Label & Values */
.kpi-label {
    font-size: 0.76rem;
    font-weight: 600;
    color: #6b7280;
    letter-spacing: -0.01em;
}

.kpi-value {
    font-size: 1.65rem;
    font-weight: 700;
    line-height: 1.1;
    margin-bottom: 0.25rem;
}

.kpi-meta {
    font-size: 0.72rem;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.kpi-icon-box {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.bg-emerald-subtle { background-color: #ecfdf5; color: #059669; }
.bg-blue-subtle { background-color: #eff6ff; color: #2563eb; }
.bg-amber-subtle { background-color: #fffbeb; color: #d97706; }
.bg-green-subtle { background-color: #f0fdf4; color: #16a34a; }
.bg-purple-subtle { background-color: #f5f3ff; color: #7c3aed; }
.bg-rose-subtle { background-color: #fef2f2; color: #dc2626; }

.dot-live {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #0284c7;
    display: inline-block;
    margin-right: 4px;
}

/* Status Pipeline Bar */
.status-pipeline-bar {
    height: 12px;
    background-color: #f1f5f9;
    border-radius: 9999px;
    overflow: hidden;
    display: flex;
    width: 100%;
}
.pipeline-segment {
    height: 100%;
    transition: width 0.5s ease;
}
.seg-active { background-color: #0284c7; }
.seg-preparing { background-color: #f59e0b; }
.seg-ready { background-color: #10b981; }
.seg-completed { background-color: #8b5cf6; }
.seg-cancelled { background-color: #ef4444; }

.tabletop-status-chip {
    background-color: #f9fafb;
    border: 1px solid #f3f4f6;
    border-radius: 8px;
}
.chip-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Tabletop Table Styles */
.tabletop-table {
    border-collapse: separate;
    border-spacing: 0;
}
.tabletop-table thead th {
    background-color: #f9fafb !important;
    color: #4b5563;
    font-size: 0.78rem;
    font-weight: 600;
    border-top: none;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}
.tabletop-table tbody td {
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}
.tabletop-table tbody tr:last-child td {
    border-bottom: none;
}
.tabletop-table tbody tr:hover {
    background-color: #f9fafb !important;
}

/* Tabletop Avatar Pill */
.tabletop-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background-color: #f3f4f6;
    color: #374151;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Tabletop Status Badges */
.tabletop-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 500;
    white-space: nowrap;
}
.badge-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.badge-blue { background-color: #eff6ff; color: #1d4ed8; }
.dot-blue { background-color: #2563eb; }

.badge-amber { background-color: #fffbeb; color: #b45309; }
.dot-amber { background-color: #d97706; }

.badge-green { background-color: #ecfdf5; color: #047857; }
.dot-green { background-color: #059669; }

.badge-purple { background-color: #f5f3ff; color: #6d28d9; }
.dot-purple { background-color: #7c3aed; }

.badge-red { background-color: #fef2f2; color: #b91c1c; }
.dot-red { background-color: #dc2626; }

/* Kitchen Pending Badge */
.tabletop-pending-badge {
    display: inline-block;
    min-width: 22px;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 600;
    color: #6b7280;
    background-color: #f3f4f6;
}
.tabletop-pending-badge.badge-active {
    color: #dc2626;
    background-color: #fee2e2;
}

/* Branch Bars */
.branch-bar-row {
    margin-bottom: 0.8rem;
}
.branch-name-label {
    width: 105px;
    font-size: 0.82rem;
    font-weight: 500;
    color: #374151;
}
.branch-bar-track {
    height: 12px;
    background-color: #f3f4f6;
    border-radius: 6px;
    overflow: hidden;
}
.branch-bar-fill {
    height: 100%;
    background-color: #dc2626;
    border-radius: 6px;
    transition: width 0.5s ease;
}
.branch-count-badge {
    width: 30px;
    text-align: right;
    font-size: 0.82rem;
    font-weight: 600;
    color: #111827;
}

.tabletop-link {
    color: #dc2626;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.15s ease;
}
.tabletop-link:hover {
    color: #b91c1c;
}

.legend-dot {
    width: 7px;
    height: 7px;
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
    el.style.transform = 'scale(1.06)';
    el.textContent = endVal.toLocaleString();

    setTimeout(() => {
        el.style.transform = 'scale(1)';
    }, 180);
}

function getStatusPillHtml(status) {
    const s = String(status || 'active').toLowerCase();
    switch (s) {
        case 'preparing':
            return '<span class="tabletop-badge badge-amber"><span class="badge-dot dot-amber"></span> Preparing</span>';
        case 'ready':
            return '<span class="tabletop-badge badge-green"><span class="badge-dot dot-green"></span> Ready</span>';
        case 'completed':
            return '<span class="tabletop-badge badge-purple"><span class="badge-dot dot-purple"></span> Completed</span>';
        case 'cancelled':
            return '<span class="tabletop-badge badge-red"><span class="badge-dot dot-red"></span> Cancelled</span>';
        default:
            return '<span class="tabletop-badge badge-blue"><span class="badge-dot dot-blue"></span> Active</span>';
    }
}

async function fetchDashboardData() {
    if (isRefreshing) return;
    isRefreshing = true;

    try {
        const response = await fetch("{{ route('dashboard') }}?format=json", {
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
                trendEl.className = `kpi-meta ${isPos ? 'text-success' : 'text-danger'}`;
                trendEl.innerHTML = `<i class="bi ${isPos ? 'bi-arrow-up-short' : 'bi-arrow-down-short'}"></i><span>${isPos ? '+' : ''}${pct}% vs yest</span>`;
            }

            // 2. Update Status Pipeline
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
                    if (chip) chip.innerHTML = `${dist.active.count} <span class="text-muted fw-normal" style="font-size: 0.7rem;">(${dist.active.percentage}%)</span>`;
                }
                if (dist.preparing) {
                    setSegment('seg-preparing', dist.preparing.percentage, dist.preparing.count, 'Preparing');
                    const chip = document.getElementById('chip-prep-val');
                    if (chip) chip.innerHTML = `${dist.preparing.count} <span class="text-muted fw-normal" style="font-size: 0.7rem;">(${dist.preparing.percentage}%)</span>`;
                }
                if (dist.ready) {
                    setSegment('seg-ready', dist.ready.percentage, dist.ready.count, 'Ready');
                    const chip = document.getElementById('chip-ready-val');
                    if (chip) chip.innerHTML = `${dist.ready.count} <span class="text-muted fw-normal" style="font-size: 0.7rem;">(${dist.ready.percentage}%)</span>`;
                }
                if (dist.completed) {
                    setSegment('seg-completed', dist.completed.percentage, dist.completed.count, 'Completed');
                    const chip = document.getElementById('chip-comp-val');
                    if (chip) chip.innerHTML = `${dist.completed.count} <span class="text-muted fw-normal" style="font-size: 0.7rem;">(${dist.completed.percentage}%)</span>`;
                }
                if (dist.cancelled) {
                    setSegment('seg-cancelled', dist.cancelled.percentage, dist.cancelled.count, 'Cancelled');
                    const chip = document.getElementById('chip-canc-val');
                    if (chip) chip.innerHTML = `${dist.cancelled.count} <span class="text-muted fw-normal" style="font-size: 0.7rem;">(${dist.cancelled.percentage}%)</span>`;
                }
            }

            // 3. Update Branch-wise Breakdown Bars
            const branchRows = document.getElementById('branchBreakdownRows');
            if (branchRows && data.branchBreakdown) {
                if (data.branchBreakdown.length === 0) {
                    branchRows.innerHTML = `
                        <div class="text-center text-muted py-4 small">
                            <i class="bi bi-building fs-4 d-block mb-1 text-secondary opacity-50"></i>
                            No branch data found
                        </div>`;
                } else {
                    const maxVal = Math.max(1, ...data.branchBreakdown.map(b => b.total || 0));
                    let rowsHtml = '';
                    data.branchBreakdown.forEach(b => {
                        const bTotal = b.total || 0;
                        const barWidthPct = bTotal > 0 ? Math.min(100, Math.max(6, Math.round((bTotal / maxVal) * 100))) : 0;
                        rowsHtml += `
                            <div class="branch-bar-row mb-3">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="branch-name-label text-truncate" title="${escapeHtml(b.name)}">
                                        ${escapeHtml(b.name)}
                                    </div>
                                    <div class="branch-bar-track flex-grow-1 position-relative">
                                        <div class="branch-bar-fill" style="width: ${barWidthPct}%;"></div>
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

            // 4. Update Kitchen Queue
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
                            <tr>
                                <td class="ps-0 py-2.5">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="tabletop-avatar">
                                            ${escapeHtml(kInit)}
                                        </span>
                                        <span class="fw-medium text-dark small" style="font-size: 0.86rem;">
                                            ${escapeHtml(k.name)}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-secondary small py-2.5" style="font-size: 0.84rem;">${escapeHtml(k.branch_name)}</td>
                                <td class="text-end pe-0 py-2.5">
                                    <span class="tabletop-pending-badge ${isActive ? 'badge-active' : ''}">${k.pending}</span>
                                </td>
                            </tr>`;
                    });
                    kitchenBody.innerHTML = kHtml;
                }
            }

            // 5. Update Line Chart
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
                                No recent orders found
                            </td>
                        </tr>`;
                } else {
                    let oHtml = '';
                    data.recentOrders.forEach(ord => {
                        const statusPill = getStatusPillHtml(ord.status);
                        const initials = (ord.table_name || 'T1').replace(/[^a-zA-Z0-9]/g, '').substring(0, 2).toUpperCase() || 'T1';
                        oHtml += `
                            <tr>
                                <td class="ps-0 fw-semibold text-dark small py-2.5" style="font-size: 0.86rem;">${escapeHtml(ord.order_number)}</td>
                                <td class="text-secondary small py-2.5" style="font-size: 0.84rem;">${escapeHtml(ord.branch_name)}</td>
                                <td class="py-2.5">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="tabletop-avatar">${escapeHtml(initials)}</span>
                                        <div>
                                            <div class="fw-medium text-dark small" style="line-height: 1.2;">${escapeHtml(ord.table_name)}</div>
                                            <div class="text-muted" style="font-size: 0.72rem;">${escapeHtml(ord.waiter_name || 'Staff')}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center py-2.5">${statusPill}</td>
                                <td class="text-end pe-0 text-muted small py-2.5" style="font-size: 0.82rem;">${escapeHtml(ord.time)}</td>
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
    // 1. Initialize Order Trend Line Chart (Clean Tabletop Style)
    const ctx = document.getElementById('orderTrendChart');
    if (ctx) {
        const labels = {!! $hourlyLabelsJson !!};
        const todayData = {!! $hourlyTrendJson !!};
        const yesterdayData = {!! $yesterdayHourlyTrendJson !!};

        const chartContext = ctx.getContext('2d');
        const gradient = chartContext.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.22)');
        gradient.addColorStop(0.8, 'rgba(16, 185, 129, 0.02)');
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
                        tension: 0.4,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2,
                        pointRadius: 3.5,
                        pointHoverRadius: 5.5,
                    },
                    {
                        label: 'Yesterday',
                        data: yesterdayData,
                        borderColor: '#a7f3d0',
                        borderWidth: 1.6,
                        borderDash: [4, 4],
                        fill: false,
                        tension: 0.4,
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
                        backgroundColor: '#111827',
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 8,
                        cornerRadius: 6,
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
                            color: '#6b7280',
                            font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" }
                        }
                    },
                    y: {
                        min: 0,
                        suggestedMax: 10,
                        grid: {
                            color: '#f3f4f6',
                            drawBorder: false,
                        },
                        ticks: {
                            stepSize: 5,
                            color: '#6b7280',
                            font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" }
                        }
                    }
                }
            }
        });
    }

    // 2. Real-time background sync every 8 seconds
    pollingInterval = setInterval(() => {
        if (!document.hidden) {
            fetchDashboardData();
        }
    }, 8000);

    // Immediate sync on visibility change
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            fetchDashboardData();
        }
    });
});
</script>
@endpush
@endsection
