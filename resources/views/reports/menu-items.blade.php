@extends('layouts.app')

@section('title', 'Menu Items Report')

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
            <h1 class="h3 fw-bold text-dark mb-1 mt-2">Menu Items Report</h1>
            <p class="text-muted small mb-0">Detailed analytical tracking of menu dish popularity, quantities ordered, and revenue generated.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('reports.menu-items.export', request()->all()) }}" class="btn btn-outline-success d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    {{-- 2. Summary KPI Metrics Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Menu Items --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Total Dishes</span>
                    <span class="badge bg-primary-subtle text-primary rounded-circle p-2">
                        <i class="bi bi-egg-fried"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-dark">{{ number_format($metrics['total_items']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Menu catalog size</span>
            </div>
        </div>

        {{-- Total Quantity Sold --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Qty Ordered</span>
                    <span class="badge bg-info-subtle text-info rounded-circle p-2">
                        <i class="bi bi-bag-check"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-dark">{{ number_format($metrics['total_quantity_sold']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Total portions ordered</span>
            </div>
        </div>

        {{-- Total Revenue --}}
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Menu Revenue</span>
                    <span class="badge bg-success-subtle text-success rounded-circle p-2">
                        <i class="bi bi-currency-rupee"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-success">₹{{ number_format((float) $metrics['total_revenue'], 2) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Excluding cancellations</span>
            </div>
        </div>

        {{-- Top Seller --}}
        <div class="col-6 col-md-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Top Seller</span>
                    <span class="badge bg-warning-subtle text-warning rounded-circle p-2">
                        <i class="bi bi-trophy"></i>
                    </span>
                </div>
                <div class="fs-6 fw-bold text-dark text-truncate" title="{{ $metrics['top_seller'] }}">{{ $metrics['top_seller'] }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Highest ordered dish</span>
            </div>
        </div>

        {{-- Cancelled Quantity --}}
        <div class="col-12 col-md-6 col-xl-2">
            <div class="card h-100 border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-muted small text-uppercase fw-bold">Cancelled Qty</span>
                    <span class="badge bg-danger-subtle text-danger rounded-circle p-2">
                        <i class="bi bi-x-circle"></i>
                    </span>
                </div>
                <div class="fs-4 fw-bold text-danger">{{ number_format($metrics['total_cancelled']) }}</div>
                <span class="text-muted" style="font-size: 0.76rem;">Voided items</span>
            </div>
        </div>
    </div>

    {{-- 3. Filter Bar Card --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4 no-print">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.menu-items') }}" id="reportFilterForm">
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

                    {{-- Category --}}
                    <div class="col-6 col-md-2 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Category</label>
                        <select name="category_id" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string)$filters['category_id'] === (string)$cat->id)>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Menu Type --}}
                    <div class="col-6 col-md-2 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Menu Type</label>
                        <select name="menu_type_id" class="form-select form-select-sm">
                            <option value="">All Menu Types</option>
                            @foreach($menuTypes as $mt)
                                <option value="{{ $mt->id }}" @selected((string)$filters['menu_type_id'] === (string)$mt->id)>
                                    {{ $mt->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

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

                    {{-- Search Text --}}
                    <div class="col-12 col-md-3 col-xl-2">
                        <label class="form-label small fw-bold text-muted mb-1">Search</label>
                        <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="Dish name, code...">
                    </div>

                    {{-- Filter & Reset Buttons --}}
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">
                            <i class="bi bi-funnel me-1"></i> Apply
                        </button>
                        <a href="{{ route('reports.menu-items') }}" class="btn btn-sm btn-outline-secondary px-3">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 4. Report Data Table Card (Pure Analytical View, No Action Column) --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                <i class="bi bi-table me-2 text-primary"></i>Menu Items Sales List ({{ $menuItems->total() }} records)
            </h5>
            <span class="text-muted small">Showing page {{ $menuItems->currentPage() }} of {{ max(1, $menuItems->lastPage()) }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 report-table">
                <thead class="table-light">
                    <tr class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.04em;">
                        <th style="width: 50px;">#</th>
                        <th>Dish Code</th>
                        <th>Dish Name</th>
                        <th>Category</th>
                        <th>Menu Type</th>
                        <th>Assigned Kitchen</th>
                        <th class="text-end">Selling Price</th>
                        <th class="text-center">Orders</th>
                        <th class="text-center">Qty Ordered</th>
                        <th class="text-center">Cancelled</th>
                        <th class="text-center">Net Sold</th>
                        <th class="text-end">Total Revenue (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($menuItems as $index => $item)
                        @php
                            $ordered = (int) ($item->total_quantity_ordered ?? 0);
                            $cancelled = (int) ($item->total_cancelled_quantity ?? 0);
                            $netSold = max(0, $ordered - $cancelled);
                            $revenue = (float) ($item->total_revenue ?? 0.00);
                            $kitchens = $item->kitchenMenuItems->map(fn($k) => $k->kitchen?->name)->filter()->unique();
                        @endphp
                        <tr>
                            {{-- # --}}
                            <td class="text-muted small">
                                {{ $menuItems->firstItem() + $index }}
                            </td>

                            {{-- Dish Code --}}
                            <td>
                                <span class="badge bg-light text-dark font-monospace border">
                                    {{ $item->code ?: '—' }}
                                </span>
                            </td>

                            {{-- Dish Name --}}
                            <td>
                                <strong class="text-dark">{{ $item->name }}</strong>
                                @if(!$item->is_active)
                                    <span class="badge bg-danger-subtle text-danger ms-1" style="font-size: 0.7rem;">Inactive</span>
                                @endif
                                @if($item->branch)
                                    <small class="d-block text-muted" style="font-size: 0.74rem;">
                                        {{ $item->branch->name }}
                                    </small>
                                @endif
                            </td>

                            {{-- Category --}}
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ $item->category?->name ?? '—' }}
                                </span>
                            </td>

                            {{-- Menu Type --}}
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    {{ $item->menuType?->name ?? '—' }}
                                </span>
                            </td>

                            {{-- Assigned Kitchen --}}
                            <td>
                                @if($kitchens->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($kitchens as $kName)
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-record-circle text-primary me-1"></i>{{ $kName }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>

                            {{-- Selling Price --}}
                            <td class="text-end fw-semibold text-dark">
                                ₹{{ number_format((float) $item->selling_price, 2) }}
                            </td>

                            {{-- Orders Count --}}
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary border">
                                    {{ $item->orders_count ?? 0 }} orders
                                </span>
                            </td>

                            {{-- Qty Ordered --}}
                            <td class="text-center fw-bold text-dark">
                                {{ $ordered }}
                            </td>

                            {{-- Cancelled Qty --}}
                            <td class="text-center">
                                @if($cancelled > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $cancelled }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>

                            {{-- Net Sold --}}
                            <td class="text-center">
                                @if($netSold > 0)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold px-2.5 py-1">
                                        {{ $netSold }}
                                    </span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>

                            {{-- Total Revenue --}}
                            <td class="text-end fw-bold text-success fs-6">
                                ₹{{ number_format($revenue, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-5">
                                <div class="py-4">
                                    <i class="bi bi-file-earmark-x text-muted" style="font-size: 2.5rem;"></i>
                                    <h6 class="fw-bold text-dark mt-2">No Menu Items Found</h6>
                                    <p class="text-muted small mb-3">No dishes match the selected category, kitchen, or search criteria.</p>
                                    <a href="{{ route('reports.menu-items') }}" class="btn btn-sm btn-outline-primary">
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
        @if($menuItems->hasPages())
            <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center no-print">
                <span class="text-muted small">
                    Showing {{ $menuItems->firstItem() }} to {{ $menuItems->lastItem() }} of {{ $menuItems->total() }} dishes
                </span>
                <div>
                    {{ $menuItems->links() }}
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

