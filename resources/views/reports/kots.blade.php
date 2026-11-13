@extends('layouts.app')

@section('title', 'KOT Report')

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
            <h1 class="h3 fw-bold text-dark mb-1 mt-2">KOT Report</h1>
            <p class="text-muted small mb-0">Detailed analytical tracking of Kitchen Order Tickets (KOT), kitchen station routing, and preparation progress.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('reports.kots.export', request()->all()) }}" class="btn btn-outline-success d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    {{-- 2. Summary KPI Metrics Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total KOTs --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Total KOTs</span>
                    <span class="badge bg-primary-subtle text-primary rounded-circle p-2">
                        <i class="bi bi-receipt-cutoff"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-dark">{{ number_format($metrics['total_kots']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">In selected period</span>
            </div>
        </div>

        {{-- Served / Completed --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Served</span>
                    <span class="badge bg-success-subtle text-success rounded-circle p-2">
                        <i class="bi bi-check2-circle"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-success">{{ number_format($metrics['served']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Fulfilled tickets</span>
            </div>
        </div>

        {{-- Ready for Pickup --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Ready</span>
                    <span class="badge bg-info-subtle text-info rounded-circle p-2">
                        <i class="bi bi-bell"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-info">{{ number_format($metrics['ready']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Awaiting server pickup</span>
            </div>
        </div>

        {{-- Active In Kitchen --}}
        <div class="col-6 col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">In Kitchen</span>
                    <span class="badge bg-warning-subtle text-warning rounded-circle p-2">
                        <i class="bi bi-fire"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-warning-emphasis">{{ number_format($metrics['in_progress']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Accepted / Preparing</span>
            </div>
        </div>

        {{-- Cancelled Tickets --}}
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Cancelled</span>
                    <span class="badge bg-danger-subtle text-danger rounded-circle p-2">
                        <i class="bi bi-x-circle"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-danger">{{ number_format($metrics['cancelled']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Voided KOT tickets</span>
            </div>
        </div>
    </div>

    {{-- 3. Filter Bar Card --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4 no-print">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.kots') }}" id="reportFilterForm">
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

                    {{-- Ticket Status --}}
                    <div class="col-6 col-md-2 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Ticket Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="all">All Statuses</option>
                            @foreach(['new' => 'New', 'accepted' => 'Accepted', 'preparing' => 'Preparing', 'ready' => 'Ready', 'served' => 'Served', 'cancelled' => 'Cancelled'] as $stVal => $stLabel)
                                <option value="{{ $stVal }}" @selected($filters['status'] === $stVal)>{{ $stLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Table --}}
                    <div class="col-6 col-md-2 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Table</label>
                        <select name="table_id" class="form-select form-select-sm">
                            <option value="">All Tables</option>
                            @foreach($tables as $tbl)
                                <option value="{{ $tbl->id }}" @selected((string)$filters['table_id'] === (string)$tbl->id)>
                                    {{ $tbl->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Search Text --}}
                    <div class="col-12 col-md-3 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Search</label>
                        <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="KOT #, order, dish...">
                    </div>

                    {{-- Filter & Reset Buttons --}}
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">
                            <i class="bi bi-funnel me-1"></i> Apply
                        </button>
                        <a href="{{ route('reports.kots') }}" class="btn btn-sm btn-outline-secondary px-3">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 4. Report Data Table Card (Pure Analytical View, No Action Column, No Location Icon) --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                <i class="bi bi-table me-2 text-primary"></i>KOT Tickets List ({{ $kots->total() }} records)
            </h5>
            <span class="text-muted small">Showing page {{ $kots->currentPage() }} of {{ max(1, $kots->lastPage()) }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 report-table">
                <thead class="table-light">
                    <tr class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.04em;">
                        <th style="width: 50px;">#</th>
                        <th>KOT Number</th>
                        <th>Order #</th>
                        <th>Kitchen Station</th>
                        <th>Branch</th>
                        <th>Table</th>
                        <th>Waiter</th>
                        <th>Items Summary</th>
                        <th class="text-center">Total Qty</th>
                        <th class="text-center">Status</th>
                        <th>Date &amp; Time</th>
                        <th class="text-end">Prep Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($kots as $index => $kot)
                        <tr>
                            {{-- # --}}
                            <td class="text-muted small">
                                {{ $kots->firstItem() + $index }}
                            </td>

                            {{-- KOT Number --}}
                            <td>
                                <span class="badge bg-dark font-monospace text-white px-2 py-1">
                                    {{ $kot->kot_number }}
                                </span>
                            </td>

                            {{-- Order Number --}}
                            <td>
                                @if($kot->order)
                                    <a href="{{ route('orders.show', $kot->order) }}" class="fw-bold font-monospace text-primary text-decoration-none">
                                        {{ $kot->order->order_number }}
                                    </a>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>

                            {{-- Kitchen Station --}}
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-record-circle text-primary me-1"></i>{{ $kot->kitchen?->name ?? '—' }}
                                </span>
                            </td>

                            {{-- Branch (Clean Text, No Location Icon) --}}
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ $kot->branch?->name ?? '—' }}
                                </span>
                            </td>

                            {{-- Table --}}
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-grid-3x3-gap me-1"></i>{{ $kot->order?->table?->name ?? '—' }}
                                </span>
                                @if($kot->order?->table?->floor)
                                    <small class="d-block text-muted">{{ $kot->order->table->floor->name }}</small>
                                @endif
                            </td>

                            {{-- Waiter --}}
                            <td>
                                <div class="small fw-semibold text-dark">{{ $kot->order?->waiter?->name ?? '—' }}</div>
                            </td>

                            {{-- Items Summary --}}
                            <td>
                                <div class="small text-dark" style="max-width: 260px;">
                                    @foreach($kot->items as $item)
                                        <div class="text-truncate">
                                            <strong>{{ $item->quantity }}×</strong> {{ $item->menuItem?->name ?? 'Dish' }}
                                            @if($item->special_instructions)
                                                <span class="text-danger small">({{ $item->special_instructions }})</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Total Quantity --}}
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary border fw-bold">
                                    {{ $kot->items->sum('quantity') }}
                                </span>
                            </td>

                            {{-- Status --}}
                            <td class="text-center">
                                @php
                                    $statusClass = match(strtolower($kot->ticket_status)) {
                                        'served', 'completed' => 'bg-success text-white',
                                        'ready' => 'bg-info text-dark',
                                        'preparing' => 'bg-warning text-dark',
                                        'accepted', 'new' => 'bg-primary text-white',
                                        'cancelled' => 'bg-danger text-white',
                                        default => 'bg-secondary text-white',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }} text-capitalize px-2.5 py-1">
                                    {{ $kot->ticket_status }}
                                </span>
                            </td>

                            {{-- Date & Time --}}
                            <td>
                                <div class="fw-semibold text-dark">{{ $kot->created_at->format('d M Y') }}</div>
                                <div class="text-muted small">{{ $kot->created_at->format('h:i A') }}</div>
                            </td>

                            {{-- Prep Time / Duration --}}
                            <td class="text-end">
                                @if($kot->duration_seconds)
                                    <span class="badge bg-light text-dark border">
                                        {{ gmdate('i:s', $kot->duration_seconds) }}
                                    </span>
                                @elseif($kot->accepted_at && $kot->ready_at)
                                    <span class="badge bg-light text-dark border">
                                        {{ $kot->ready_at->diffInMinutes($kot->accepted_at) }} mins
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-5">
                                <div class="py-4">
                                    <i class="bi bi-file-earmark-x text-muted" style="font-size: 2.5rem;"></i>
                                    <h6 class="fw-bold text-dark mt-2">No KOT Tickets Found</h6>
                                    <p class="text-muted small mb-3">No kitchen tickets match the selected filters or time period.</p>
                                    <a href="{{ route('reports.kots') }}" class="btn btn-sm btn-outline-primary">
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
        @if($kots->hasPages())
            <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center no-print">
                <span class="text-muted small">
                    Showing {{ $kots->firstItem() }} to {{ $kots->lastItem() }} of {{ $kots->total() }} tickets
                </span>
                <div>
                    {{ $kots->links() }}
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

