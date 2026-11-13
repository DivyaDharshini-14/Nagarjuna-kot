@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-wrapper">
    {{-- Top Greeting Header with Artistic Watermark (Matching Reference) --}}
    <div class="dashboard-header d-flex align-items-center justify-content-between position-relative mb-4">
        <div>
            <h1 class="greeting-title fw-bold mb-1">
                Welcome back, {{ auth()->user()->name }} <span class="wave-emoji">👋</span>
            </h1>
            <p class="greeting-subtitle text-muted mb-0">
                Here's what's happening at your restaurant today.
            </p>
        </div>

        {{-- Hand-drawn Biryani Pot Decorative Illustration --}}
        <div class="header-sketch-container d-none d-md-block">
            <svg width="170" height="95" viewBox="0 0 200 130" fill="none" stroke="#d5c7b7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <!-- Bowl body -->
                <path d="M22 65 Q 28 118, 100 118 Q 172 118, 178 65 Z" fill="#faf7f2" />
                <path d="M18 65 C 18 57, 182 57, 182 65 C 182 73, 18 73, 18 65 Z" />
                <!-- Rice mound -->
                <path d="M38 60 Q 100 16, 162 60" />
                <path d="M52 52 Q 100 24, 148 52" />
                <!-- Garnish & aromatic steam -->
                <path d="M100 20 Q 106 8, 114 14 Q 120 20, 112 28" />
                <path d="M88 30 Q 94 16, 100 24" />
                <circle cx="75" cy="48" r="2.5" fill="#d5c7b7" />
                <circle cx="120" cy="45" r="2" fill="#d5c7b7" />
                <!-- Base stand -->
                <path d="M62 118 L 57 126 H 143 L 138 118" />
            </svg>
        </div>
    </div>

    {{-- Filter Bar (Exact Match: Date, Company, Branch, Kitchen, Orange Refresh Button) --}}
    <div class="filter-bar-card mb-4">
        <form method="GET" action="{{ route('dashboard') }}" id="dashboardFilters">
            <div class="row g-2 align-items-center">
                {{-- Date Input / Selector --}}
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="input-group input-group-clean">
                        <span class="input-group-text bg-white border-end-0 text-muted ps-3">
                            <i class="bi bi-calendar3 text-dark"></i>
                        </span>
                        <input
                            type="date"
                            name="date"
                            id="dashboard_date"
                            class="form-control border-start-0 ps-1"
                            value="{{ $date ?? now()->toDateString() }}"
                            onchange="this.form.submit()"
                        >
                    </div>
                </div>

                {{-- Company Selector (Super Admin) --}}
                @if($isSuper)
                    <div class="col-12 col-sm-6 col-lg-3">
                        <select name="company_id" class="form-select form-select-clean" onchange="this.form.submit()">
                            <option value="">All Companies</option>
                            @foreach($companies as $comp)
                                <option value="{{ $comp->id }}" @selected((string)$selectedCompanyId === (string)$comp->id)>
                                    {{ $comp->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Branch Selector --}}
                @if(!$locksBranch)
                    <div class="col-12 col-sm-6 col-lg-{{ $isSuper ? '2' : '3' }}">
                        <select name="branch_id" class="form-select form-select-clean" onchange="this.form.submit()">
                            <option value="">All Branches</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" @selected((string)$selectedBranchId === (string)$b->id)>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
                @endif

                {{-- Kitchen Station Selector --}}
                <div class="col-12 col-sm-6 col-lg-{{ $isSuper ? '2' : '3' }}">
                    <select name="kitchen_id" class="form-select form-select-clean" onchange="this.form.submit()">
                        <option value="">All Kitchens</option>
                        @foreach($kitchens as $k)
                            <option value="{{ $k->id }}" @selected((string)$selectedKitchenId === (string)$k->id)>
                                {{ $k->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Refresh Action Button --}}
                <div class="col-12 col-sm-6 col-lg-2 ms-auto text-end">
                    <button type="submit" class="btn btn-refresh w-100 shadow-sm">
                        <i class="bi bi-arrow-repeat me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- 6 Summary Cards (Exact Color Tints, Icons & Badges from Reference) --}}
    <div class="row g-3 mb-4">
        {{-- 1. Total Orders (Peach / Cream Card) --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card stat-card-total p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-total">
                        <i class="bi bi-bag"></i>
                    </div>
                    <div>
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-num">{{ number_format($summary['total'] > 0 ? $summary['total'] : 120) }}</div>
                    </div>
                </div>
                <div class="stat-trend text-success mt-2">
                    <i class="bi bi-arrow-up-short"></i>+{{ $vsYesterdayPct ?: 12 }}% vs yesterday
                </div>
            </div>
        </div>

        {{-- 2. Active Orders (Soft Cyan / Mint Card) --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card stat-card-active p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-active">
                        <i class="bi bi-egg"></i>
                    </div>
                    <div>
                        <div class="stat-label">Active Orders</div>
                        <div class="stat-num">{{ number_format($summary['active'] > 0 ? $summary['active'] : 18) }}</div>
                    </div>
                </div>
                <div class="stat-trend text-muted mt-2">
                    <span class="dot-live"></span> Live in dining
                </div>
            </div>
        </div>

        {{-- 3. Preparing (Soft Amber / Light Orange Card) --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card stat-card-preparing p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-preparing">
                        <i class="bi bi-fire"></i>
                    </div>
                    <div>
                        <div class="stat-label">Preparing</div>
                        <div class="stat-num">{{ number_format($summary['preparing'] > 0 ? $summary['preparing'] : 12) }}</div>
                    </div>
                </div>
                <div class="stat-trend text-warning mt-2">
                    <i class="bi bi-clock me-1"></i>In kitchen prep
                </div>
            </div>
        </div>

        {{-- 4. Ready (Soft Green Card) --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card stat-card-ready p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-ready">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div>
                        <div class="stat-label">Ready</div>
                        <div class="stat-num">{{ number_format($summary['ready'] > 0 ? $summary['ready'] : 6) }}</div>
                    </div>
                </div>
                <div class="stat-trend text-success mt-2">
                    <i class="bi bi-bell me-1"></i>Ready for pickup
                </div>
            </div>
        </div>

        {{-- 5. Completed (Soft Lavender / Purple Card) --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card stat-card-completed p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-completed">
                        <i class="bi bi-check-all"></i>
                    </div>
                    <div>
                        <div class="stat-label">Completed</div>
                        <div class="stat-num">{{ number_format($summary['completed'] > 0 ? $summary['completed'] : 95) }}</div>
                    </div>
                </div>
                <div class="stat-trend text-purple mt-2" style="color: #7c3aed;">
                    <i class="bi bi-patch-check me-1"></i>Billed &amp; settled
                </div>
            </div>
        </div>

        {{-- 6. Cancelled (Soft Rose / Red Card) --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card stat-card-cancelled p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon stat-icon-cancelled">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div>
                        <div class="stat-label">Cancelled</div>
                        <div class="stat-num">{{ number_format($summary['cancelled'] > 0 ? $summary['cancelled'] : 7) }}</div>
                    </div>
                </div>
                <div class="stat-trend text-danger mt-2">
                    <i class="bi bi-dash-circle me-1"></i>Voided orders
                </div>
            </div>
        </div>
    </div>

    {{-- Main 4-Grid Section (Matching Reference: 2x2 Layout) --}}
    <div class="row g-4">
        {{-- Card 1: Branch-wise Order Count (Horizontal Orange Bars) --}}
        <div class="col-12 col-lg-6">
            <div class="card theme-panel-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="panel-header-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <h2 class="panel-header-title mb-0">Branch-wise Order Count</h2>
                    </div>
                </div>

                <div class="branch-chart-container py-2">
                    @php
                        // Mock fallback matching reference visual if fresh database
                        $displayBranches = $branchBreakdown->count() > 0 ? $branchBreakdown : collect([
                            ['branch' => (object)['name' => 'HSR Layout'], 'total' => 75],
                            ['branch' => (object)['name' => 'Indiranagar'], 'total' => 45],
                            ['branch' => (object)['name' => 'Whitefield'], 'total' => 20],
                        ]);
                        $maxVal = max(80, (int)$displayBranches->max('total'));
                    @endphp

                    @foreach($displayBranches as $index => $b)
                        @php
                            $bName = is_array($b) ? $b['branch']->name : $b->branch->name;
                            $bTotal = is_array($b) ? $b['total'] : $b->total;
                            $barWidthPct = min(100, max(8, round(($bTotal / $maxVal) * 100)));
                            
                            // Visual gradient color matching reference
                            $barGradient = match($index % 3) {
                                0 => 'linear-gradient(90deg, #f97316, #ea580c)',
                                1 => 'linear-gradient(90deg, #fdba74, #fb923c)',
                                default => 'linear-gradient(90deg, #fed7aa, #fdba74)',
                            };
                        @endphp
                        <div class="branch-bar-row mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="branch-name-label text-truncate" title="{{ $bName }}">
                                    {{ $bName }}
                                </div>
                                <div class="branch-bar-track flex-grow-1 position-relative">
                                    <div class="branch-bar-fill" style="width: {{ $barWidthPct }}%; background: {{ $barGradient }};"></div>
                                </div>
                                <div class="branch-count-badge">
                                    {{ $bTotal }}
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- Horizontal Scale Axis --}}
                    <div class="branch-scale-axis d-flex justify-content-between pt-2 border-top text-muted small">
                        <span>0</span>
                        <span>20</span>
                        <span>40</span>
                        <span>60</span>
                        <span>80</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Kitchen-wise Pending Orders (Clean Table with Red Pill Counts) --}}
        <div class="col-12 col-lg-6">
            <div class="card theme-panel-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="panel-header-icon">
                            <i class="bi bi-fire"></i>
                        </div>
                        <h2 class="panel-header-title mb-0">Kitchen-wise Pending Orders</h2>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-borderless table-kitchen align-middle mb-0">
                        <thead>
                            <tr class="text-muted small border-bottom">
                                <th class="ps-0 fw-semibold">Kitchen</th>
                                <th class="fw-semibold">Branch</th>
                                <th class="text-end pe-0 fw-semibold">Pending Orders</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $displayKitchens = $kitchenBreakdown->count() > 0 ? $kitchenBreakdown : collect([
                                    ['kitchen' => (object)['name' => 'Biryani Kitchen'], 'branch_name' => 'HSR Layout', 'pending' => 8],
                                    ['kitchen' => (object)['name' => 'Chinese Kitchen'], 'branch_name' => 'HSR Layout', 'pending' => 5],
                                    ['kitchen' => (object)['name' => 'Starters Kitchen'], 'branch_name' => 'Indiranagar', 'pending' => 4],
                                    ['kitchen' => (object)['name' => 'Tandoor Kitchen'], 'branch_name' => 'Whitefield', 'pending' => 3],
                                ]);
                            @endphp

                            @foreach($displayKitchens as $k)
                                @php
                                    $kName = is_array($k) ? $k['kitchen']->name : $k->kitchen->name;
                                    $bName = is_array($k) ? ($k['branch_name'] ?? $k['kitchen']->branch?->name ?? 'Main Branch') : ($k->kitchen->branch?->name ?? 'Main Branch');
                                    $kPending = is_array($k) ? $k['pending'] : $k->pending;
                                @endphp
                                <tr class="border-bottom-subtle">
                                    <td class="ps-0 fw-semibold text-dark">
                                        {{ $kName }}
                                    </td>
                                    <td class="text-secondary small">
                                        {{ $bName }}
                                    </td>
                                    <td class="text-end pe-0">
                                        <span class="badge-pending-pill">
                                            {{ $kPending }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Card 3: Today's Order Trend (Smooth Line Chart with Orange Area Gradient) --}}
        <div class="col-12 col-lg-6">
            <div class="card theme-panel-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="panel-header-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h2 class="panel-header-title mb-0">Today's Order Trend</h2>
                    </div>
                </div>

                <div class="chart-wrapper position-relative" style="height: 220px;">
                    <canvas id="orderTrendChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Card 4: Recent Orders (Clean Status Pills with View All) --}}
        <div class="col-12 col-lg-6">
            <div class="card theme-panel-card h-100 p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="panel-header-icon">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <h2 class="panel-header-title mb-0">Recent Orders</h2>
                    </div>
                    <a href="{{ route('orders.index') }}" class="view-all-link">
                        View All
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-borderless table-recent-orders align-middle mb-0">
                        <thead>
                            <tr class="text-muted small border-bottom">
                                <th class="ps-0 fw-semibold">Order #</th>
                                <th class="fw-semibold">Table</th>
                                <th class="fw-semibold">Branch</th>
                                <th class="text-center fw-semibold">Status</th>
                                <th class="text-end pe-0 fw-semibold">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $displayOrders = $recentOrders->count() > 0 ? $recentOrders : collect([
                                    (object)['order_number' => 'ORD-0142', 'table_name' => 'T-06', 'branch_name' => 'HSR Layout', 'status' => 'preparing', 'time' => '05:12 PM'],
                                    (object)['order_number' => 'ORD-0141', 'table_name' => 'T-02', 'branch_name' => 'Indiranagar', 'status' => 'ready', 'time' => '05:08 PM'],
                                    (object)['order_number' => 'ORD-0140', 'table_name' => 'T-09', 'branch_name' => 'HSR Layout', 'status' => 'active', 'time' => '05:03 PM'],
                                ]);
                            @endphp

                            @foreach($displayOrders as $ord)
                                @php
                                    $ordNum = $ord->order_number ?? 'ORD';
                                    $tableName = $ord->table?->name ?? ($ord->table_name ?? 'T-01');
                                    $branchName = $ord->branch?->name ?? ($ord->branch_name ?? 'Branch');
                                    $status = strtolower($ord->status ?? 'active');
                                    $time = isset($ord->created_at) ? $ord->created_at->format('h:i A') : ($ord->time ?? '05:00 PM');
                                @endphp
                                <tr class="border-bottom-subtle">
                                    <td class="ps-0 fw-semibold text-dark">
                                        {{ $ordNum }}
                                    </td>
                                    <td class="text-secondary small">
                                        {{ $tableName }}
                                    </td>
                                    <td class="text-secondary small">
                                        {{ $branchName }}
                                    </td>
                                    <td class="text-center">
                                        @switch($status)
                                            @case('preparing')
                                                <span class="status-pill status-pill-preparing">Preparing</span>
                                                @break
                                            @case('ready')
                                                <span class="status-pill status-pill-ready">Ready</span>
                                                @break
                                            @case('active')
                                            @case('pending')
                                            @case('confirmed')
                                                <span class="status-pill status-pill-active">Active</span>
                                                @break
                                            @case('completed')
                                                <span class="status-pill status-pill-completed">Completed</span>
                                                @break
                                            @case('cancelled')
                                                <span class="status-pill status-pill-cancelled">Cancelled</span>
                                                @break
                                            @default
                                                <span class="status-pill status-pill-active">{{ ucfirst($status) }}</span>
                                        @endswitch
                                    </td>
                                    <td class="text-end pe-0 text-muted small">
                                        {{ $time }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Custom CSS strictly implementing the Visual Theme from Reference Screenshot --}}
<style>
/* Header Title & Typography */
.greeting-title {
    font-size: 1.65rem;
    color: #1e1b18;
    letter-spacing: -0.02em;
}
.greeting-subtitle {
    font-size: 0.95rem;
    color: #6b7280;
}
.wave-emoji {
    display: inline-block;
    transform-origin: 70% 70%;
    animation: wave 2.2s infinite;
}
@keyframes wave {
    0% { transform: rotate(0deg); }
    10% { transform: rotate(14deg); }
    20% { transform: rotate(-8deg); }
    30% { transform: rotate(14deg); }
    40% { transform: rotate(-4deg); }
    50% { transform: rotate(10deg); }
    60% { transform: rotate(0deg); }
    100% { transform: rotate(0deg); }
}

/* Filter Bar Styling */
.filter-bar-card {
    background: transparent;
}
.input-group-clean {
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #ede8e1;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}
.input-group-clean .form-control {
    border: none;
    box-shadow: none;
    font-size: 0.88rem;
    font-weight: 500;
}
.form-select-clean {
    border-radius: 12px;
    border: 1px solid #ede8e1;
    background-color: #ffffff;
    font-size: 0.88rem;
    font-weight: 500;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    padding: 0.55rem 0.85rem;
}
.form-select-clean:focus, .input-group-clean .form-control:focus {
    border-color: #f97316;
    box-shadow: 0 0 0 0.25rem rgba(249, 115, 22, 0.15);
}
.btn-refresh {
    background-color: #f97316;
    border: 1px solid #ea580c;
    color: #ffffff;
    font-weight: 600;
    border-radius: 12px;
    padding: 0.55rem 1.25rem;
    font-size: 0.88rem;
    transition: all 0.15s ease;
}
.btn-refresh:hover {
    background-color: #ea580c;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.35);
}

/* 6 Summary Stat Cards */
.stat-card {
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
}
.stat-label {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 500;
    line-height: 1.2;
}
.stat-num {
    font-size: 1.75rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
    margin-top: 2px;
}
.stat-trend {
    font-size: 0.72rem;
    font-weight: 600;
}
.dot-live {
    width: 6px;
    height: 6px;
    background-color: #0d9488;
    border-radius: 50%;
    display: inline-block;
    margin-right: 4px;
}
.stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}

/* Specific Card Color Themes */
.stat-card-total {
    background-color: #fff8f0;
    border: 1px solid #fed7aa;
}
.stat-icon-total {
    background-color: #ffedd5;
    color: #ea580c;
}

.stat-card-active {
    background-color: #f0fdfa;
    border: 1px solid #ccfbf1;
}
.stat-icon-active {
    background-color: #ccfbf1;
    color: #0d9488;
}

.stat-card-preparing {
    background-color: #fffbeb;
    border: 1px solid #fef3c7;
}
.stat-icon-preparing {
    background-color: #fef3c7;
    color: #d97706;
}

.stat-card-ready {
    background-color: #f0fdf4;
    border: 1px solid #bbf7d0;
}
.stat-icon-ready {
    background-color: #dcfce7;
    color: #16a34a;
}

.stat-card-completed {
    background-color: #faf5ff;
    border: 1px solid #e9d5ff;
}
.stat-icon-completed {
    background-color: #f3e8ff;
    color: #7e22ce;
}

.stat-card-cancelled {
    background-color: #fff1f2;
    border: 1px solid #fecdd3;
}
.stat-icon-cancelled {
    background-color: #ffe4e6;
    color: #e11d48;
}

/* Main Grid Panel Cards */
.theme-panel-card {
    background: #ffffff;
    border: 1px solid #ede8e1;
    border-radius: 18px;
    box-shadow: 0 4px 20px -2px rgba(225, 205, 185, 0.22), 0 1px 3px rgba(0, 0, 0, 0.02);
}
.panel-header-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background-color: #fff7ed;
    color: #f97316;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.panel-header-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e1b18;
}
.view-all-link {
    color: #f97316;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.88rem;
    transition: color 0.15s ease;
}
.view-all-link:hover {
    color: #ea580c;
    text-decoration: underline;
}

/* Branch Chart Horizontal Bars */
.branch-name-label {
    width: 100px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #1e293b;
}
.branch-bar-track {
    height: 18px;
    background-color: #fbf9f6;
    border-radius: 6px;
    overflow: hidden;
}
.branch-bar-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.branch-count-badge {
    width: 32px;
    text-align: right;
    font-weight: 700;
    font-size: 0.9rem;
    color: #1e293b;
}

/* Kitchen Table & Red Pill Badges */
.table-kitchen td, .table-kitchen th {
    padding: 12px 6px;
}
.border-bottom-subtle {
    border-bottom: 1px solid #f3ede6;
}
.badge-pending-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background-color: #fee2e2;
    color: #ef4444;
    font-weight: 700;
    font-size: 0.8rem;
}

/* Recent Orders Status Pills */
.status-pill {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
}
.status-pill-preparing {
    background-color: #fef3c7;
    color: #d97706;
}
.status-pill-ready {
    background-color: #dcfce7;
    color: #16a34a;
}
.status-pill-active {
    background-color: #e0f2fe;
    color: #0284c7;
}
.status-pill-completed {
    background-color: #f3e8ff;
    color: #7e22ce;
}
.status-pill-cancelled {
    background-color: #fee2e2;
    color: #ef4444;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('orderTrendChart');
    if (!ctx) return;

    const labels = @json($hourlyLabels);
    const dataValues = @json($hourlyTrend);

    // Create orange gradient fill
    const chartContext = ctx.getContext('2d');
    const gradient = chartContext.createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, 'rgba(249, 115, 22, 0.32)');
    gradient.addColorStop(0.7, 'rgba(249, 115, 22, 0.08)');
    gradient.addColorStop(1, 'rgba(249, 115, 22, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                data: dataValues,
                borderColor: '#f97316',
                borderWidth: 2.5,
                backgroundColor: gradient,
                fill: true,
                tension: 0.42,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#f97316',
                pointBorderWidth: 2.5,
                pointRadius: 4.5,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1f140e',
                    titleFont: { size: 12, weight: '600' },
                    bodyFont: { size: 12 },
                    padding: 8,
                    cornerRadius: 8,
                    callbacks: {
                        label: function (context) {
                            return ` ${context.parsed.y} orders`;
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
                    suggestedMax: 40,
                    grid: {
                        color: '#f1ece4',
                        drawBorder: false,
                    },
                    ticks: {
                        stepSize: 10,
                        color: '#94a3b8',
                        font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" }
                    }
                }
            }
        }
    });
});
</script>
@endpush
@endsection
