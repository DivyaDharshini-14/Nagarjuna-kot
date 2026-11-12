@extends('layouts.app')

@section('title', 'Transaction History - Complete Stock Movement Ledger')

@section('content')
<div class="dashcart-stock-page">
    {{-- Alerts --}}
    @include('partials.alerts')

    {{-- Dashcart Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1" style="letter-spacing: -0.02em; font-size: 1.65rem;">
                Transaction History
            </h1>
            <p class="text-secondary small mb-0" style="font-size: 0.85rem;">
                Complete audit ledger tracking Opening Stock, Stock In, Order Deductions, Cancellation Reversals, Wastage, and Manual Corrections.
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
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="card p-3.5 border-0 shadow-sm mb-4 dashcart-card">
        <form method="GET" action="{{ route('stock.history') }}" id="historyFilterForm">
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
                            placeholder="Search dish, order # or reason..."
                            value="{{ $searchTerm }}"
                        >
                    </div>
                </div>

                {{-- Movement Type Filter --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    <select name="transaction_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all">All Movement Types</option>
                        <option value="opening_stock" @selected(($selectedType ?? '') === 'opening_stock')>🟢 Opening Stock</option>
                        <option value="stock_in" @selected(($selectedType ?? '') === 'stock_in')>🟢 Stock In (+)</option>
                        <option value="order_deduction" @selected(($selectedType ?? '') === 'order_deduction')>🔵 Order Deduction (-)</option>
                        <option value="order_cancellation_reversal" @selected(($selectedType ?? '') === 'order_cancellation_reversal')>🔷 Cancel Reversal (+)</option>
                        <option value="wastage" @selected(($selectedType ?? '') === 'wastage')>🔴 Wastage (-)</option>
                        <option value="damaged" @selected(($selectedType ?? '') === 'damaged')>🟠 Damaged (-)</option>
                        <option value="manual_adjustment" @selected(($selectedType ?? '') === 'manual_adjustment')>🟣 Manual Audit (±)</option>
                        <option value="availability_change" @selected(($selectedType ?? '') === 'availability_change')>⚪ Availability Change</option>
                    </select>
                </div>

                {{-- Date Preset --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    <select name="date_preset" class="form-select form-select-sm" onchange="handleDatePresetChange(this.value)">
                        <option value="all" @selected(($datePreset ?? 'all') === 'all')>All Time</option>
                        <option value="today" @selected(($datePreset ?? '') === 'today')>Today</option>
                        <option value="yesterday" @selected(($datePreset ?? '') === 'yesterday')>Yesterday</option>
                        <option value="this_week" @selected(($datePreset ?? '') === 'this_week')>This Week</option>
                        <option value="this_month" @selected(($datePreset ?? '') === 'this_month')>This Month</option>
                        <option value="custom" @selected(($datePreset ?? '') === 'custom')>Custom Range</option>
                    </select>
                </div>

                {{-- Branch --}}
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

                {{-- Custom Date Inputs --}}
                <div class="col-12 col-md-6 col-xl-3 {{ ($datePreset ?? '') === 'custom' ? '' : 'd-none' }}" id="customDateRangeInputs">
                    <div class="input-group input-group-sm">
                        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" placeholder="From">
                        <span class="input-group-text bg-light">to</span>
                        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}" placeholder="To">
                    </div>
                </div>

                <div class="col-12 col-md-auto ms-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-filter me-1"></i>Filter Ledger
                    </button>
                    <a href="{{ route('stock.history') }}" class="btn btn-sm btn-light border px-3 rounded-pill fw-semibold text-secondary">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Full Transaction Ledger Table (Dashcart Clean Table) --}}
    <div class="card p-0 border-0 shadow-sm mb-4 dashcart-card overflow-hidden">
        <div class="p-3.5 px-4 bg-white border-bottom d-flex align-items-center justify-content-between" style="border-color: #f1f5f9 !important;">
            <div>
                <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem;">
                    Prepared Dish Stock Movement Ledger
                </h2>
                <span class="text-secondary small" style="font-size: 0.78rem;">
                    Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} ledger entries
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="dashcart-thead">
                    <tr>
                        <th class="ps-4 py-3">Timestamp</th>
                        <th class="py-3">Dish / Item</th>
                        <th class="py-3">Movement Type</th>
                        <th class="py-3 text-center">Delta</th>
                        <th class="py-3 text-center">Balance Before &rarr; After</th>
                        <th class="py-3">Staff User</th>
                        <th class="pe-4 py-3">Reference / Order / Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        @php
                            $dish = $tx->menuItem;
                            $delta = (int) $tx->quantity_delta;
                            $type = $tx->transaction_type;
                        @endphp
                        <tr>
                            {{-- Date & Time --}}
                            <td class="ps-4 py-3">
                                <div class="fw-bold text-dark" style="font-size: 0.85rem;">
                                    {{ $tx->created_at?->format('d M Y, h:i A') }}
                                </div>
                                <div class="text-secondary" style="font-size: 0.72rem;">
                                    {{ $tx->created_at?->diffForHumans() }}
                                </div>
                            </td>

                            {{-- Dish Name & Branch --}}
                            <td class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="dashcart-avatar" style="width: 30px; height: 30px; font-size: 0.75rem;">
                                        {{ strtoupper(substr($dish?->name ?? 'D', 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $dish?->name ?? 'Unknown Dish' }}</div>
                                        <div class="text-secondary small" style="font-size: 0.75rem;">
                                            <span class="font-monospace text-muted">{{ $dish?->code ?? 'NO-CODE' }}</span>
                                            &bull; {{ $dish?->branch?->name ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Movement Type --}}
                            <td class="py-3">
                                @if($type === 'stock_in' || $type === 'opening_stock')
                                    <span class="dashcart-pill pill-in">
                                        <span class="pill-dot dot-green"></span> {{ $type === 'opening_stock' ? 'Opening Stock' : 'Stock In' }}
                                    </span>
                                @elseif($type === 'order_deduction')
                                    <span class="dashcart-pill pill-grey" style="color: #0284c7; background-color: #e0f2fe; border-color: #bae6fd;">
                                        <span class="pill-dot" style="background-color: #0284c7;"></span> Order Deduction
                                    </span>
                                @elseif($type === 'order_cancellation_reversal')
                                    <span class="dashcart-pill pill-in" style="color: #0d9488; background-color: #ccfbf1; border-color: #99f6e4;">
                                        <span class="pill-dot" style="background-color: #0d9488;"></span> Cancel Reversal
                                    </span>
                                @elseif($type === 'wastage')
                                    <span class="dashcart-pill pill-out">
                                        <span class="pill-dot dot-red"></span> Wastage
                                    </span>
                                @elseif($type === 'damaged')
                                    <span class="dashcart-pill pill-low">
                                        <span class="pill-dot dot-yellow"></span> Damaged
                                    </span>
                                @elseif($type === 'manual_adjustment')
                                    <span class="dashcart-pill pill-grey">
                                        <span class="pill-dot dot-grey"></span> Manual Audit
                                    </span>
                                @elseif($type === 'availability_change')
                                    <span class="dashcart-pill pill-grey">
                                        <span class="pill-dot dot-grey"></span> Availability
                                    </span>
                                @else
                                    <span class="dashcart-pill pill-grey">
                                        <span class="pill-dot dot-grey"></span> {{ ucwords(str_replace('_', ' ', $type)) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Delta --}}
                            <td class="py-3 text-center">
                                @if($delta > 0)
                                    <span class="fw-bold text-emerald fs-6">+{{ $delta }}</span>
                                @elseif($delta < 0)
                                    <span class="fw-bold text-rose fs-6">{{ $delta }}</span>
                                @else
                                    <span class="text-secondary">0</span>
                                @endif
                            </td>

                            {{-- Balance Before -> After --}}
                            <td class="py-3 text-center">
                                <span class="fw-bold text-dark">{{ $tx->stock_before ?? 0 }}</span>
                                <span class="text-muted mx-1">&rarr;</span>
                                <span class="fw-bold {{ ($tx->stock_after ?? 0) <= 0 ? 'text-rose' : 'text-emerald' }}">
                                    {{ $tx->stock_after ?? 0 }}
                                </span>
                                <span class="text-secondary small ms-0.5">portions</span>
                            </td>

                            {{-- Staff User --}}
                            <td class="py-3">
                                <span class="fw-semibold text-dark">{{ $tx->user?->name ?? 'System Process' }}</span>
                            </td>

                            {{-- Reference / Order / Reason --}}
                            <td class="pe-4 py-3 text-secondary">
                                @if($tx->order)
                                    <a href="{{ route('orders.show', $tx->order) }}" class="badge bg-light text-primary border text-decoration-none me-1.5 fw-semibold" title="View Order">
                                        <i class="bi bi-receipt me-1"></i>#{{ $tx->order->order_number }}
                                    </a>
                                @endif
                                <span class="small">{{ $tx->reason ?: '—' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-secondary">
                                <i class="bi bi-journal-x fs-2 text-muted mb-2 d-block"></i>
                                <div class="fw-semibold text-dark">No transaction records found</div>
                                <span class="small text-muted">Try adjusting your date range or movement type filter.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="p-3 px-4 bg-white border-top d-flex align-items-center justify-content-between" style="border-color: #f1f5f9 !important;">
                <div class="small text-secondary">
                    Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}
                </div>
                <div>
                    {{ $transactions->links() }}
                </div>
            </div>
        @endif
    </div>

    {{-- Shared Stock Modals --}}
    @include('stock.partials.modals')
</div>

<script>
function handleDatePresetChange(val) {
    const customDiv = document.getElementById('customDateRangeInputs');
    if (!customDiv) return;
    if (val === 'custom') {
        customDiv.classList.remove('d-none');
    } else {
        customDiv.classList.add('d-none');
        document.getElementById('historyFilterForm').submit();
    }
}
</script>
@endsection
