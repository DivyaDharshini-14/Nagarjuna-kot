@extends('layouts.app')

@section('title', 'Dish Availability - Live Kitchen Dispatch')

@section('content')
<div class="availability-module-page">
    {{-- Alerts --}}
    @include('partials.alerts')

    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1" style="letter-spacing: -0.02em; font-size: 1.65rem;">
                Dish Availability
            </h1>
            <p class="text-secondary small mb-0" style="font-size: 0.85rem;">
                Control live ordering availability for kitchen dishes. Changes synchronize immediately with the Waiter Mobile App.
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

            <span class="badge rounded-pill bg-emerald-subtle text-emerald border border-emerald-subtle px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.75rem;">
                <span class="live-pulse-dot"></span>
                <span>Mobile App Synced</span>
            </span>
        </div>
    </div>

    {{-- 4 Minimalist Metric Cards --}}
    <div class="row g-3 mb-4">
        {{-- Total Dishes --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="dashcart-stat-label">Total Catalog Dishes</span>
                    <div class="dashcart-icon-circle bg-sky-subtle text-sky">
                        <i class="bi bi-cup-hot"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between">
                    <span class="dashcart-stat-number text-dark">{{ number_format($totalCount ?? 0) }}</span>
                    <span class="text-secondary small" style="font-size: 0.75rem;">All stations</span>
                </div>
            </div>
        </div>

        {{-- Available --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="dashcart-stat-label">Available for Ordering</span>
                    <div class="dashcart-icon-circle bg-emerald-subtle text-emerald">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between">
                    <span class="dashcart-stat-number text-emerald">{{ number_format($availableCount ?? 0) }}</span>
                    <span class="text-secondary small" style="font-size: 0.75rem;">Orderable in app</span>
                </div>
            </div>
        </div>

        {{-- Temporarily Unavailable --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="dashcart-stat-label">Temporarily Paused</span>
                    <div class="dashcart-icon-circle bg-amber-subtle text-amber">
                        <i class="bi bi-pause-circle"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between">
                    <span class="dashcart-stat-number text-amber">{{ number_format($tempCount ?? 0) }}</span>
                    <span class="text-secondary small" style="font-size: 0.75rem;">Short kitchen hold</span>
                </div>
            </div>
        </div>

        {{-- Sold Out --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="dashcart-stat-label">Sold Out (86'd)</span>
                    <div class="dashcart-icon-circle bg-rose-subtle text-rose">
                        <i class="bi bi-slash-circle"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between">
                    <span class="dashcart-stat-number text-rose">{{ number_format($soldOutCount ?? 0) }}</span>
                    <span class="text-secondary small" style="font-size: 0.75rem;">Blocked in app</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="card p-3.5 border-0 shadow-sm mb-4 dashcart-card">
        <form method="GET" action="{{ route('stock.availability-board') }}" id="availFilterForm">
            {{-- Quick Status Filter Segment --}}
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-3 mb-3 border-bottom border-light-subtle">
                <div class="d-flex flex-wrap align-items-center gap-1.5">
                    <a href="{{ route('stock.availability-board', array_merge(request()->except('availability', 'page'))) }}"
                       class="btn btn-sm quick-tab-pill {{ empty($selectedAvailability) ? 'active' : '' }}">
                        All Dishes <span class="badge rounded-pill ms-1.5">{{ $totalCount ?? 0 }}</span>
                    </a>
                    <a href="{{ route('stock.availability-board', array_merge(request()->except('page'), ['availability' => 'available'])) }}"
                       class="btn btn-sm quick-tab-pill {{ $selectedAvailability === 'available' ? 'active-emerald' : '' }}">
                        <span class="pill-dot dot-green me-1"></span>Available <span class="badge rounded-pill ms-1.5">{{ $availableCount ?? 0 }}</span>
                    </a>
                    <a href="{{ route('stock.availability-board', array_merge(request()->except('page'), ['availability' => 'temporarily_unavailable'])) }}"
                       class="btn btn-sm quick-tab-pill {{ $selectedAvailability === 'temporarily_unavailable' ? 'active-amber' : '' }}">
                        <span class="pill-dot dot-yellow me-1"></span>Temp. Unavailable <span class="badge rounded-pill ms-1.5">{{ $tempCount ?? 0 }}</span>
                    </a>
                    <a href="{{ route('stock.availability-board', array_merge(request()->except('page'), ['availability' => 'sold_out'])) }}"
                       class="btn btn-sm quick-tab-pill {{ $selectedAvailability === 'sold_out' ? 'active-rose' : '' }}">
                        <span class="pill-dot dot-red me-1"></span>Sold Out <span class="badge rounded-pill ms-1.5">{{ $soldOutCount ?? 0 }}</span>
                    </a>
                </div>

                <a href="{{ route('stock.availability-board') }}" class="small text-secondary fw-semibold text-decoration-none">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>

            {{-- Filter Selectors --}}
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
                            placeholder="Search dish name or code..."
                            value="{{ $searchTerm }}"
                        >
                    </div>
                </div>

                {{-- Kitchen Station --}}
                <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                    <select name="kitchen_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Kitchen Stations</option>
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
                        <option value="in_stock" @selected(($selectedStockStatus ?? '') === 'in_stock')>In Stock (> 5)</option>
                        <option value="low_stock" @selected(($selectedStockStatus ?? '') === 'low_stock')>Low Stock (1-5)</option>
                        <option value="out_of_stock" @selected(($selectedStockStatus ?? '') === 'out_of_stock')>Zero Stock (0)</option>
                        <option value="untracked" @selected(($selectedStockStatus ?? '') === 'untracked')>Untracked</option>
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
            </div>
        </form>
    </div>

    {{-- Main Availability Table --}}
    <div class="card p-0 border-0 shadow-sm mb-4 dashcart-card overflow-hidden">
        <div class="p-3.5 px-4 bg-white border-bottom d-flex align-items-center justify-content-between" style="border-color: #f1f5f9 !important;">
            <div>
                <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem;">
                    Kitchen Dispatch &amp; Availability Roster
                </h2>
                <span class="text-secondary small" style="font-size: 0.78rem;">
                    Showing {{ $items->firstItem() ?? 0 }} to {{ $items->lastItem() ?? 0 }} of {{ $items->total() }} dishes
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.86rem;">
                <thead class="dashcart-thead">
                    <tr>
                        <th class="ps-4 py-3">Dish / Item</th>
                        <th class="py-3">Kitchen &amp; Branch</th>
                        <th class="py-3 text-center">Current Stock</th>
                        <th class="py-3 text-center">Last Updated</th>
                        <th class="py-3 text-center">Live Status</th>
                        <th class="pe-4 py-3 text-end" style="min-width: 260px;">Availability Action</th>
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
                        @endphp
                        <tr id="dish-row-{{ $dish->id }}">
                            {{-- Dish Details --}}
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
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Kitchen & Branch --}}
                            <td class="py-3">
                                <div class="small fw-semibold text-dark">
                                    <i class="bi bi-geo-alt text-muted me-1"></i>{{ $dish->branch?->name ?? '—' }}
                                </div>
                                <div class="mt-0.5">
                                    @forelse($kitchensList as $kName)
                                        <span class="badge bg-light text-secondary border me-1 small" style="font-size: 0.68rem;">{{ $kName }}</span>
                                    @empty
                                        <span class="text-muted small" style="font-size: 0.7rem;">Unmapped Station</span>
                                    @endforelse
                                </div>
                            </td>

                            {{-- Current Stock --}}
                            <td class="py-3 text-center">
                                @if($isTracked && $hasStock)
                                    <div class="fw-bolder fs-6 {{ $isOut ? 'text-rose' : ($isLow ? 'text-amber' : 'text-emerald') }}">
                                        {{ $stockQty }}
                                    </div>
                                    <span class="text-secondary" style="font-size: 0.7rem;">portions</span>
                                @elseif($isTracked)
                                    <span class="text-muted small">Not set</span>
                                @else
                                    <span class="badge rounded-pill bg-light text-secondary border px-2 py-0.5" style="font-size: 0.7rem;">
                                        Untracked
                                    </span>
                                @endif
                            </td>

                            {{-- Last Updated --}}
                            <td class="py-3 text-center">
                                <div class="text-dark small fw-medium" id="updated-time-{{ $dish->id }}" title="{{ $dish->updated_at?->format('d M Y, h:i A') }}">
                                    {{ $dish->updated_at?->diffForHumans() ?? '—' }}
                                </div>
                                <div class="text-secondary" style="font-size: 0.7rem;">
                                    {{ $dish->updated_at?->format('h:i A') }}
                                </div>
                            </td>

                            {{-- Live Status Pill --}}
                            <td class="py-3 text-center" id="status-pill-container-{{ $dish->id }}">
                                @if($avail === 'available')
                                    <span class="dashcart-pill pill-in">
                                        <span class="pill-dot dot-green"></span> Available
                                    </span>
                                @elseif($avail === 'temporarily_unavailable')
                                    <span class="dashcart-pill pill-low">
                                        <span class="pill-dot dot-yellow"></span> Temp. Unavailable
                                    </span>
                                @else
                                    <span class="dashcart-pill pill-out">
                                        <span class="pill-dot dot-red"></span> Sold Out
                                    </span>
                                @endif
                            </td>

                            {{-- Availability Action: Clean 3-State Segment Control --}}
                            <td class="pe-4 py-3 text-end">
                                <div class="d-inline-flex align-items-center gap-1.5">
                                    <div class="avail-segment-control" id="segment-control-{{ $dish->id }}">
                                        {{-- 1. Available --}}
                                        <button
                                            type="button"
                                            class="segment-btn btn-avail {{ $avail === 'available' ? 'active' : '' }}"
                                            onclick="updateAvailabilityDirect({{ $dish->id }}, 'available', '{{ addslashes($dish->name) }}')"
                                            title="Mark Available"
                                        >
                                            <span class="pill-dot dot-green"></span>
                                            <span>Available</span>
                                        </button>

                                        {{-- 2. Temp Unavailable --}}
                                        <button
                                            type="button"
                                            class="segment-btn btn-temp {{ $avail === 'temporarily_unavailable' ? 'active' : '' }}"
                                            onclick="updateAvailabilityDirect({{ $dish->id }}, 'temporarily_unavailable', '{{ addslashes($dish->name) }}')"
                                            title="Mark Temporarily Unavailable"
                                        >
                                            <span class="pill-dot dot-yellow"></span>
                                            <span>Temp</span>
                                        </button>

                                        {{-- 3. Sold Out --}}
                                        <button
                                            type="button"
                                            class="segment-btn btn-sold {{ $avail === 'sold_out' ? 'active' : '' }}"
                                            onclick="updateAvailabilityDirect({{ $dish->id }}, 'sold_out', '{{ addslashes($dish->name) }}')"
                                            title="Mark Sold Out"
                                        >
                                            <span class="pill-dot dot-red"></span>
                                            <span>Sold Out</span>
                                        </button>
                                    </div>

                                    {{-- Custom reason modal trigger --}}
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-dashcart-icon text-secondary"
                                        onclick="openAvailabilityModal({{ $dish->id }}, '{{ addslashes($dish->name) }}', '{{ $dish->availability }}')"
                                        title="Update with custom reason note"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-secondary">
                                <i class="bi bi-inbox fs-2 text-muted mb-2 d-block"></i>
                                <div class="fw-semibold text-dark">No dishes match the selected filters</div>
                                <span class="small text-muted">Try clearing the search query or station filters.</span>
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

    {{-- Live Toast Container for Instant Sync Feedback --}}
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
        <div id="availabilityToast" class="toast align-items-center text-white bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border-radius: 12px;">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2 py-2.5 px-3">
                    <i class="bi bi-check-circle-fill text-success fs-6"></i>
                    <span id="toastMessage" style="font-size: 0.84rem;"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2.5 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashcart Card Styling */
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

.live-pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #10b981;
    display: inline-block;
    animation: livePulse 2s infinite;
}
@keyframes livePulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Quick Filter Tab Pills */
.quick-tab-pill {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.35rem 0.85rem;
    display: inline-flex;
    align-items: center;
    transition: all 0.15s ease;
    text-decoration: none;
}
.quick-tab-pill:hover {
    background-color: #f1f5f9;
    color: #0f172a;
}
.quick-tab-pill.active {
    background-color: #0f172a !important;
    border-color: #0f172a !important;
    color: #ffffff !important;
}
.quick-tab-pill.active .badge {
    background-color: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
}
.quick-tab-pill.active-emerald {
    background-color: #10b981 !important;
    border-color: #10b981 !important;
    color: #ffffff !important;
}
.quick-tab-pill.active-amber {
    background-color: #f59e0b !important;
    border-color: #f59e0b !important;
    color: #ffffff !important;
}
.quick-tab-pill.active-rose {
    background-color: #ef4444 !important;
    border-color: #ef4444 !important;
    color: #ffffff !important;
}

.quick-tab-pill .badge {
    background-color: #e2e8f0;
    color: #475569;
    font-size: 0.7rem;
    padding: 0.15rem 0.45rem;
}

/* Table Avatar & Head */
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

/* 3-Segment Availability Action Control */
.avail-segment-control {
    display: inline-flex;
    background-color: #f1f5f9;
    border-radius: 8px;
    padding: 2px;
    border: 1px solid #e2e8f0;
}
.segment-btn {
    border: none;
    background: transparent;
    border-radius: 6px;
    padding: 0.25rem 0.55rem;
    font-size: 0.74rem;
    font-weight: 600;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    transition: all 0.15s ease;
    cursor: pointer;
}
.segment-btn:hover:not(.active) {
    color: #0f172a;
    background-color: rgba(255, 255, 255, 0.5);
}
.segment-btn.active.btn-avail {
    background-color: #ffffff;
    color: #047857 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    font-weight: 700;
}
.segment-btn.active.btn-temp {
    background-color: #ffffff;
    color: #b45309 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    font-weight: 700;
}
.segment-btn.active.btn-sold {
    background-color: #ffffff;
    color: #b91c1c !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    font-weight: 700;
}

.btn-dashcart-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    transition: all 0.15s ease;
}
.btn-dashcart-icon:hover {
    background-color: #f8fafc;
    border-color: #cbd5e1;
}
</style>

<script>
// One-click instant AJAX availability update with optimistic UI & toast notification
function updateAvailabilityDirect(dishId, newState, dishName) {
    const segment = document.getElementById(`segment-control-${dishId}`);
    if (!segment) return;

    // Optimistically update segment buttons
    const buttons = segment.querySelectorAll('.segment-btn');
    buttons.forEach(b => b.classList.remove('active'));

    if (newState === 'available') {
        segment.querySelector('.btn-avail')?.classList.add('active');
    } else if (newState === 'temporarily_unavailable') {
        segment.querySelector('.btn-temp')?.classList.add('active');
    } else if (newState === 'sold_out') {
        segment.querySelector('.btn-sold')?.classList.add('active');
    }

    // Call API
    fetch(`/stock/${dishId}/availability`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            availability: newState,
            reason: `Direct status toggle to ${newState.replace('_', ' ')} via Dish Availability board`
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update status pill
            const pillContainer = document.getElementById(`status-pill-container-${dishId}`);
            if (pillContainer) {
                if (newState === 'available') {
                    pillContainer.innerHTML = '<span class="dashcart-pill pill-in"><span class="pill-dot dot-green"></span> Available</span>';
                } else if (newState === 'temporarily_unavailable') {
                    pillContainer.innerHTML = '<span class="dashcart-pill pill-low"><span class="pill-dot dot-yellow"></span> Temp. Unavailable</span>';
                } else {
                    pillContainer.innerHTML = '<span class="dashcart-pill pill-out"><span class="pill-dot dot-red"></span> Sold Out</span>';
                }
            }

            // Update updated-at time
            const timeEl = document.getElementById(`updated-time-${dishId}`);
            if (timeEl && data.updated_at_formatted) {
                timeEl.textContent = data.updated_at_formatted;
            }

            // Show Toast
            showSyncToast(`${dishName} is now ${data.availability_label}. Waiter mobile app synced.`);
        } else {
            alert(data.message || 'Failed to update availability.');
            location.reload();
        }
    })
    .catch(err => {
        console.error('Error updating availability:', err);
        alert('Network error while updating availability.');
        location.reload();
    });
}

function showSyncToast(msg) {
    const toastEl = document.getElementById('availabilityToast');
    const toastMsg = document.getElementById('toastMessage');
    if (!toastEl || !toastMsg) return;
    toastMsg.textContent = msg;
    const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
}
</script>
@endsection
