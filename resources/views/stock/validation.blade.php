@extends('layouts.app')

@section('title', 'Stock Validation - Negative Stock Guard & Audit')

@section('content')
<div class="dashcart-stock-page">
    {{-- Alerts --}}
    @include('partials.alerts')

    {{-- Dashcart Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1" style="letter-spacing: -0.02em; font-size: 1.65rem;">
                Stock Validation
            </h1>
            <p class="text-secondary small mb-0" style="font-size: 0.85rem;">
                Negative stock guard engine, threshold safety margin verification, and inventory discrepancy auditing.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button
                type="button"
                class="btn btn-sm btn-primary rounded-pill px-3.5 py-1.5 fw-semibold shadow-sm d-inline-flex align-items-center gap-1.5"
                onclick="document.getElementById('validationFilterForm').submit()"
            >
                <i class="bi bi-shield-check"></i>
                <span>Run Audit</span>
            </button>
        </div>
    </div>

    {{-- Dashcart 4 Compact KPI Cards --}}
    <div class="row g-3 mb-4">
        {{-- 1. Healthy Stock --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Healthy Stock (> 5)</span>
                        <div class="dashcart-icon-circle bg-emerald-subtle text-emerald">
                            <i class="bi bi-shield-check"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number text-emerald">
                        {{ number_format(max(0, $summary['tracked_dishes'] - $summary['low_stock'] - $summary['out_of_stock'])) }}
                    </div>
                </div>
                <div class="pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        <i class="bi bi-check2 text-emerald me-1"></i>Safe buffer for customer ordering
                    </span>
                </div>
            </div>
        </div>

        {{-- 2. Low Stock --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Low Stock (1-5)</span>
                        <div class="dashcart-icon-circle bg-amber-subtle text-amber">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number {{ $summary['low_stock'] > 0 ? 'text-amber' : 'text-dark' }}">
                        {{ number_format($summary['low_stock']) }}
                    </div>
                </div>
                <div class="pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        <i class="bi bi-fire text-amber me-1"></i>Requires kitchen batch replenish
                    </span>
                </div>
            </div>
        </div>

        {{-- 3. Zero Stock --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Zero Stock (Blocked)</span>
                        <div class="dashcart-icon-circle bg-rose-subtle text-rose">
                            <i class="bi bi-slash-circle"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number {{ $summary['out_of_stock'] > 0 ? 'text-rose' : 'text-dark' }}">
                        {{ number_format($summary['out_of_stock']) }}
                    </div>
                </div>
                <div class="pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        <i class="bi bi-lock-fill text-rose me-1"></i>Orders rejected automatically
                    </span>
                </div>
            </div>
        </div>

        {{-- 4. Discrepancies --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Discrepancies</span>
                        <div class="dashcart-icon-circle bg-sky-subtle text-sky">
                            <i class="bi bi-patch-question"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number {{ $summary['discrepancies'] > 0 ? 'text-sky' : 'text-dark' }}">
                        {{ number_format($summary['discrepancies']) }}
                    </div>
                </div>
                <div class="pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        Zero stock but marked Available
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="card p-3.5 border-0 shadow-sm mb-4 dashcart-card">
        <form method="GET" action="{{ route('stock.validation') }}" id="validationFilterForm">
            <div class="row g-2.5 align-items-center">
                {{-- Search --}}
                <div class="col-12 col-md-5 col-xl-4">
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

                {{-- Validation Severity Filter --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    <select name="validation_status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all">All Validation States</option>
                        <option value="healthy" @selected($validationStatus === 'healthy')>🟢 Healthy Stock (> 5)</option>
                        <option value="warning" @selected($validationStatus === 'warning')>🟡 Low Stock (1-5)</option>
                        <option value="critical" @selected($validationStatus === 'critical')>🔴 Zero Stock / Blocked (0)</option>
                        <option value="discrepancy" @selected($validationStatus === 'discrepancy')>⚠️ Discrepancies (Sync Needed)</option>
                        <option value="untracked" @selected($validationStatus === 'untracked')>⚪ Untracked Items</option>
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

                {{-- Kitchen --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    <select name="kitchen_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Kitchen Stations</option>
                        @foreach($kitchens as $k)
                            <option value="{{ $k->id }}" @selected((string)$selectedKitchenId === (string)$k->id)>{{ $k->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-auto ms-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-shield-check me-1"></i>Run Audit
                    </button>
                    <a href="{{ route('stock.validation') }}" class="btn btn-sm btn-light border px-3 rounded-pill fw-semibold text-secondary">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Validation Table (Dashcart Clean Table) --}}
    <div class="card p-0 border-0 shadow-sm mb-4 dashcart-card overflow-hidden">
        <div class="p-3.5 px-4 bg-white border-bottom d-flex align-items-center justify-content-between" style="border-color: #f1f5f9 !important;">
            <div>
                <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem;">
                    Stock Validation &amp; Negative Stock Guard Table
                </h2>
                <span class="text-secondary small" style="font-size: 0.78rem;">
                    Showing {{ $items->firstItem() ?? 0 }} to {{ $items->lastItem() ?? 0 }} of {{ $items->total() }} audited dishes
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.86rem;">
                <thead class="dashcart-thead">
                    <tr>
                        <th class="ps-4 py-3">Dish / Item</th>
                        <th class="py-3">Branch &amp; Kitchen</th>
                        <th class="py-3 text-center">Ready Stock</th>
                        <th class="py-3 text-center">Mobile Availability</th>
                        <th class="py-3 text-center">Negative Stock Guard Status</th>
                        <th class="pe-4 py-3 text-end">Audit Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $dish)
                        @php
                            $isTracked = (bool) $dish->stock_tracking_enabled;
                            $stockQty = $dish->prepared_stock_quantity;
                            $hasStock = $stockQty !== null;
                            $isLow = $isTracked && $hasStock && $stockQty <= 5 && $stockQty > 0;
                            $isZero = $isTracked && $hasStock && $stockQty <= 0;
                            $isDiscrepancy = $isTracked && $hasStock && (
                                ($dish->availability === 'available' && $stockQty <= 0) ||
                                ($dish->availability === 'sold_out' && $stockQty > 0)
                            );
                        @endphp
                        <tr>
                            {{-- Dish Avatar & Name --}}
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="dashcart-avatar">
                                        {{ strtoupper(substr($dish->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.9rem;">
                                            {{ $dish->name }}
                                        </div>
                                        <div class="text-secondary font-monospace" style="font-size: 0.75rem;">
                                            {{ $dish->code ?? 'NO-CODE' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Branch & Kitchen --}}
                            <td class="py-3">
                                <div class="small fw-semibold text-dark">{{ $dish->branch?->name ?? '—' }}</div>
                                <div class="small text-secondary">
                                    {{ $dish->kitchenMenuItems->pluck('kitchen.name')->join(', ') ?: 'Unmapped' }}
                                </div>
                            </td>

                            {{-- Ready Stock --}}
                            <td class="py-3 text-center">
                                @if($isTracked && $hasStock)
                                    <span class="fs-6 fw-bold {{ $isZero ? 'text-rose' : ($isLow ? 'text-amber' : 'text-emerald') }}">
                                        {{ $stockQty }}
                                    </span>
                                    <span class="text-secondary small">portions</span>
                                @elseif($isTracked)
                                    <span class="text-muted small">Not set</span>
                                @else
                                    <span class="badge bg-light text-secondary border small">Untracked</span>
                                @endif
                            </td>

                            {{-- Mobile Availability --}}
                            <td class="py-3 text-center">
                                @if($dish->availability === 'available')
                                    <span class="dashcart-pill pill-in"><span class="pill-dot dot-green"></span> Available</span>
                                @elseif($dish->availability === 'temporarily_unavailable')
                                    <span class="dashcart-pill pill-low"><span class="pill-dot dot-yellow"></span> Temp. Unavailable</span>
                                @else
                                    <span class="dashcart-pill pill-out"><span class="pill-dot dot-red"></span> Sold Out</span>
                                @endif
                            </td>

                            {{-- Negative Stock Guard Status --}}
                            <td class="py-3 text-center">
                                @if(!$isTracked)
                                    <span class="badge bg-light text-secondary border px-2.5 py-1">
                                        <i class="bi bi-dash-circle me-1"></i>Stock Tracking Inactive
                                    </span>
                                @elseif($isDiscrepancy && $dish->availability === 'available')
                                    <span class="badge bg-danger text-white px-2.5 py-1">
                                        <i class="bi bi-exclamation-octagon me-1"></i>0 Stock but Marked Available!
                                    </span>
                                @elseif($isDiscrepancy && $dish->availability === 'sold_out')
                                    <span class="badge bg-warning text-dark px-2.5 py-1">
                                        <i class="bi bi-info-circle me-1"></i>Has Stock ({{ $stockQty }}) but Marked Sold Out
                                    </span>
                                @elseif($isZero)
                                    <span class="dashcart-pill pill-out">
                                        <span class="pill-dot dot-red"></span> Zero Stock Guard Active (Blocked)
                                    </span>
                                @elseif($isLow)
                                    <span class="dashcart-pill pill-low">
                                        <span class="pill-dot dot-yellow"></span> Low Stock Threshold (&le; 5)
                                    </span>
                                @else
                                    <span class="dashcart-pill pill-in">
                                        <span class="pill-dot dot-green"></span> Passed · Safe Ordering Margin
                                    </span>
                                @endif
                            </td>

                            {{-- Audit Actions --}}
                            <td class="pe-4 py-3 text-end">
                                <div class="d-inline-flex align-items-center gap-1.5">
                                    {{-- If discrepancy (0 stock & available), quick mark sold out --}}
                                    @if($isDiscrepancy && $dish->availability === 'available' && $canManageStock)
                                        <form method="POST" action="{{ route('stock.availability', $dish) }}" class="d-inline m-0">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="availability" value="sold_out">
                                            <input type="hidden" name="reason" value="System Validation: Auto-sync sold out due to zero stock balance">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2.5 rounded-pill fw-semibold" style="font-size: 0.75rem;" title="Sync state to Sold Out">
                                                <i class="bi bi-slash-circle me-1"></i>Mark Sold Out
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Quick Stock In button --}}
                                    @if($canManageStock && $isTracked)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-dashcart-emerald"
                                            onclick="openStockInModalSingle({{ $dish->id }}, '{{ addslashes($dish->name) }}', {{ (int)($dish->prepared_stock_quantity ?? 0) }})"
                                            title="Add prepared batch / adjust"
                                        >
                                            <i class="bi bi-plus-lg me-1"></i>Stock In
                                        </button>
                                    @endif

                                    {{-- Ledger modal --}}
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-dashcart-icon text-secondary"
                                        onclick="openHistoryModal({{ $dish->id }}, '{{ addslashes($dish->name) }}')"
                                        title="View History Ledger"
                                    >
                                        <i class="bi bi-journal-text"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-secondary">
                                <i class="bi bi-shield-check fs-2 text-emerald mb-2 d-block"></i>
                                <div class="fw-semibold text-dark">No validation issues found for the current selection</div>
                                <span class="small text-muted">All dishes meet safety criteria or match the filter parameters.</span>
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
@endsection
