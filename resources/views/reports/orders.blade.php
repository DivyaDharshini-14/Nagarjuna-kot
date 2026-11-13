@extends('layouts.app')

@section('title', 'Order Report')

@section('content')
<div class="report-wrapper">
    {{-- 1. Page Header with Title & Export Actions --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1.5 rounded-pill">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports Module
                </span>
            </div>
            <h1 class="h3 fw-bold text-dark mb-1 mt-2">Order Report</h1>
            <p class="text-muted small mb-0">Detailed analytical tracking of customer orders, kitchen routing, and sales metrics.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('reports.orders.export', request()->all()) }}" class="btn btn-outline-success d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    {{-- 2. Summary KPI Metrics Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Orders --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Total Orders</span>
                    <span class="badge bg-primary-subtle text-primary rounded-circle p-2">
                        <i class="bi bi-receipt"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-dark">{{ number_format($metrics['total_orders']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">In selected period</span>
            </div>
        </div>

        {{-- Total Revenue --}}
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Total Value</span>
                    <span class="badge bg-success-subtle text-success rounded-circle p-2">
                        <i class="bi bi-currency-rupee"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-success">₹{{ number_format((float) $metrics['total_revenue'], 2) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Excluding cancellations</span>
            </div>
        </div>

        {{-- Completed / Served --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Served</span>
                    <span class="badge bg-info-subtle text-info rounded-circle p-2">
                        <i class="bi bi-check2-circle"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-dark">{{ number_format($metrics['completed']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Fully completed</span>
            </div>
        </div>

        {{-- Active / In-Progress --}}
        <div class="col-6 col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">In-Progress</span>
                    <span class="badge bg-warning-subtle text-warning rounded-circle p-2">
                        <i class="bi bi-clock-history"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-warning-emphasis">{{ number_format($metrics['in_progress']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Kitchen / Ready</span>
            </div>
        </div>

        {{-- Cancelled Orders --}}
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Cancelled</span>
                    <span class="badge bg-danger-subtle text-danger rounded-circle p-2">
                        <i class="bi bi-x-circle"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-danger">{{ number_format($metrics['cancelled']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Voided orders</span>
            </div>
        </div>
    </div>

    {{-- 3. Filter Bar Card --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4 no-print">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.orders') }}" id="reportFilterForm">
                <div class="row g-2 align-items-end">
                    {{-- Date Preset Selection --}}
                    <div class="col-12 col-md-3 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Time Period</label>
                        <select name="date_preset" id="datePreset" class="form-select form-select-sm" onchange="toggleCustomDates(this.value)">
                            <option value="all_time" @selected($filters['date_preset'] === 'all_time')>All Time</option>
                            <option value="today" @selected($filters['date_preset'] === 'today')>Today</option>
                            <option value="yesterday" @selected($filters['date_preset'] === 'yesterday')>Yesterday</option>
                            <option value="this_week" @selected($filters['date_preset'] === 'this_week')>This Week</option>
                            <option value="this_month" @selected($filters['date_preset'] === 'this_month')>This Month</option>
                            <option value="last_30_days" @selected($filters['date_preset'] === 'last_30_days')>Last 30 Days</option>
                            <option value="custom" @selected($filters['date_preset'] === 'custom')>Custom Date Range</option>
                        </select>
                    </div>

                    {{-- Custom Date Range: From --}}
                    <div class="col-6 col-md-2 custom-date-col" style="{{ $filters['date_preset'] === 'custom' ? '' : 'display:none;' }}">
                        <label class="form-label small fw-bold text-muted mb-1">From Date</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control form-control-sm">
                    </div>

                    {{-- Custom Date Range: To --}}
                    <div class="col-6 col-md-2 custom-date-col" style="{{ $filters['date_preset'] === 'custom' ? '' : 'display:none;' }}">
                        <label class="form-label small fw-bold text-muted mb-1">To Date</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control form-control-sm">
                    </div>

                    {{-- Company (Super Admin only) --}}
                    @if($isSuper)
                        <div class="col-6 col-md-2 col-xl-2">
                            <label class="form-label small fw-bold text-muted mb-1">Company</label>
                            <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">All Companies</option>
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}" @selected((string)$selectedCompanyId === (string)$c->id)>
                                        {{ $c->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Branch --}}
                    @if(!$locksBranch)
                        <div class="col-6 col-md-2 col-xl-2">
                            <label class="form-label small fw-bold text-muted mb-1">Branch</label>
                            <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">All Branches</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected((string)$selectedBranchId === (string)$b->id)>
                                        {{ $b->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Kitchen Station --}}
                    <div class="col-6 col-md-2 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Kitchen Station</label>
                        <select name="kitchen_id" class="form-select form-select-sm">
                            <option value="">All Kitchens</option>
                            @foreach($kitchens as $k)
                                <option value="{{ $k->id }}" @selected((string)$selectedKitchenId === (string)$k->id)>
                                    {{ $k->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status --}}
                    <div class="col-6 col-md-2 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Order Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="all">All Statuses</option>
                            @foreach(['new' => 'New', 'accepted' => 'Accepted', 'preparing' => 'Preparing', 'ready' => 'Ready', 'served' => 'Served', 'cancelled' => 'Cancelled'] as $stVal => $stLabel)
                                <option value="{{ $stVal }}" @selected($filters['status'] === $stVal)>{{ $stLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Search Text --}}
                    <div class="col-12 col-md-3 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Search</label>
                        <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="Order #, table, waiter...">
                    </div>

                    {{-- Filter & Reset Buttons --}}
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">
                            <i class="bi bi-funnel me-1"></i> Apply
                        </button>
                        <a href="{{ route('reports.orders') }}" class="btn btn-sm btn-outline-secondary px-3">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 4. Report Data Table Card --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                <i class="bi bi-table me-2 text-primary"></i>Orders List ({{ $orders->total() }} records)
            </h5>
            <span class="text-muted small">Showing page {{ $orders->currentPage() }} of {{ max(1, $orders->lastPage()) }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 report-table">
                <thead class="table-light">
                    <tr class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.04em;">
                        <th style="width: 50px;">#</th>
                        <th>Order Number</th>
                        <th>Date &amp; Time</th>
                        <th>Branch</th>
                        <th>Table</th>
                        <th>Waiter</th>
                        <th>Kitchens</th>
                        <th>Items Summary</th>
                        <th class="text-end">Amount</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $index => $order)
                        <tr>
                            {{-- # --}}
                            <td class="text-muted small">
                                {{ $orders->firstItem() + $index }}
                            </td>

                            {{-- Order Number --}}
                            <td>
                                <a href="{{ route('orders.show', $order) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                    {{ $order->order_number }}
                                </a>
                                @if($order->customer_name)
                                    <div class="text-muted" style="font-size: 0.76rem;">
                                        <i class="bi bi-person me-1"></i>{{ $order->customer_name }}
                                    </div>
                                @endif
                            </td>

                            {{-- Date & Time --}}
                            <td>
                                <div class="fw-semibold text-dark">{{ $order->created_at->format('d M Y') }}</div>
                                <div class="text-muted small">{{ $order->created_at->format('h:i A') }}</div>
                            </td>

                            {{-- Branch --}}
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-geo-alt me-1 text-primary"></i>{{ $order->branch?->name ?? '—' }}
                                </span>
                            </td>

                            {{-- Table --}}
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-grid-3x3-gap me-1"></i>{{ $order->table?->name ?? '—' }}
                                </span>
                                @if($order->table?->floor)
                                    <small class="d-block text-muted">{{ $order->table->floor->name }}</small>
                                @endif
                            </td>

                            {{-- Waiter --}}
                            <td>
                                <div class="small fw-semibold text-dark">{{ $order->waiter?->name ?? '—' }}</div>
                            </td>

                            {{-- Kitchens --}}
                            <td>
                                @php
                                    $uniqueKitchens = $order->kotTickets->map(fn($k) => $k->kitchen?->name)->filter()->unique();
                                @endphp
                                @if($uniqueKitchens->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($uniqueKitchens as $kName)
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-record-circle text-primary me-1"></i>{{ $kName }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>

                            {{-- Items Summary --}}
                            <td>
                                <div class="small text-dark" style="max-width: 260px;">
                                    @php
                                        $displayItems = $order->items->take(2);
                                        $remainingCount = $order->items->count() - 2;
                                    @endphp
                                    @foreach($displayItems as $it)
                                        <div class="text-truncate">
                                            <strong>{{ $it->quantity }}×</strong> {{ $it->menuItem?->name ?? 'Item' }}
                                        </div>
                                    @endforeach
                                    @if($remainingCount > 0)
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            + {{ $remainingCount }} more {{ Str::plural('item', $remainingCount) }}
                                        </div>
                                    @endif
                                </div>
                            </td>

                            {{-- Amount --}}
                            <td class="text-end fw-bold text-dark">
                                ₹{{ number_format((float) $order->total_amount, 2) }}
                            </td>

                            {{-- Status --}}
                            <td class="text-center">
                                @php
                                    $badgeClass = match(strtolower($order->status)) {
                                        'ready' => 'bg-info text-dark',
                                        'preparing' => 'bg-warning text-dark',
                                        'served', 'completed' => 'bg-success text-white',
                                        'cancelled' => 'bg-danger text-white',
                                        default => 'bg-primary text-white',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} text-capitalize px-2.5 py-1">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="py-4">
                                    <i class="bi bi-file-earmark-x text-muted" style="font-size: 2.5rem;"></i>
                                    <h6 class="fw-bold text-dark mt-2">No Orders Found</h6>
                                    <p class="text-muted small mb-3">No orders match the selected filters or date range.</p>
                                    <a href="{{ route('reports.orders') }}" class="btn btn-sm btn-outline-primary">
                                        Clear All Filters
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 5. Pagination --}}
        @if($orders->hasPages())
            <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center no-print">
                <span class="text-muted small">
                    Showing {{ $orders->firstItem() }} to {{ $orders->lastItem() }} of {{ $orders->total() }} orders
                </span>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Scripts for Dynamic Date Presets --}}
<script>
    function toggleCustomDates(val) {
        const customCols = document.querySelectorAll('.custom-date-col');
        if (val === 'custom') {
            customCols.forEach(el => el.style.display = 'block');
        } else {
            customCols.forEach(el => el.style.display = 'none');
            document.getElementById('reportFilterForm').submit();
        }
    }
</script>

{{-- Print Optimization Styles --}}
<style>
    @media print {
        .sidebar, .navbar, .top-header, .no-print, .btn, .pagination, #reportFilterForm {
            display: none !important;
        }
        body, .main-content, .report-wrapper {
            background: #ffffff !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        .report-table th, .report-table td {
            font-size: 0.78rem !important;
            padding: 4px 6px !important;
        }
    }
</style>
@endsection

