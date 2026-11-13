@extends('layouts.app')

@section('title', 'Kitchen Stock In - Prepared Dish Inventory')

@section('content')
<div class="dashcart-stock-page">
    {{-- Alerts --}}
    @include('partials.alerts')

    {{-- 7.1 Kitchen Stock In Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill bg-emerald-subtle text-emerald border border-emerald-subtle px-2.5 py-1 fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                    Section 7.1
                </span>
                <h1 class="h3 fw-bold text-dark mb-0" style="letter-spacing: -0.02em; font-size: 1.65rem;">
                    Kitchen Stock In
                </h1>
            </div>
            <p class="text-secondary small mb-0" style="font-size: 0.85rem;">
                Enter and maintain available prepared-dish quantities: Opening Stock baseline, daily cooked additions (Stock In), and live available portions.
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
                    <span>Batch Stock In Grid</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Compact Filter Panel --}}
    <div class="card p-3 border-0 shadow-sm mb-4 dashcart-card">
        <form method="GET" action="{{ route('stock.overview') }}" id="stockFilterForm">
            <div class="row g-2.5 align-items-center">
                {{-- Search --}}
                <div class="col-12 col-md-4 col-xl-3">
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
                    </select>
                </div>

                <div class="col-12 col-md-auto ms-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('stock.overview') }}" class="btn btn-sm btn-light border px-3 rounded-pill fw-semibold text-secondary">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Section 7.1 Dish Stock In Table --}}
    <div class="card p-0 border-0 shadow-sm mb-4 dashcart-card overflow-hidden">
        <div class="p-3.5 px-4 bg-white border-bottom d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2" style="border-color: #f1f5f9 !important;">
            <div>
                <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem;">
                    Prepared Dish Quantities (Section 7.1)
                </h2>
                <span class="text-secondary small" style="font-size: 0.78rem;">
                    <span class="fw-semibold text-dark">Dish</span> &bull;
                    <span class="fw-semibold text-purple">Opening Stock</span> &bull;
                    <span class="fw-semibold text-emerald">Stock Added</span> &bull;
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
                        <th class="ps-4 py-3" style="width: 32%; min-width: 250px;">Dish</th>
                        <th class="py-3 text-center" style="width: 18%; min-width: 140px;">
                            <span class="text-purple">Opening Stock</span>
                            <i class="bi bi-info-circle ms-0.5 text-muted" title="Initial baseline prepared quantity before service (7.1)" style="font-size: 0.72rem;"></i>
                        </th>
                        <th class="py-3 text-center" style="width: 18%; min-width: 140px;">
                            <span class="text-emerald">Stock Added</span>
                            <i class="bi bi-info-circle ms-0.5 text-muted" title="Additional cooked batches added today (Stock In)" style="font-size: 0.72rem;"></i>
                        </th>
                        <th class="py-3 text-center" style="width: 16%; min-width: 140px;">
                            <span class="text-dark">Available Stock</span>
                            <i class="bi bi-info-circle ms-0.5 text-muted" title="Live ready portions after order deductions" style="font-size: 0.72rem;"></i>
                        </th>
                        <th class="pe-4 py-3 text-end" style="width: 16%; min-width: 180px;">Actions</th>
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
                            {{-- 1. Dish Details --}}
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

                            {{-- 2. Opening Stock (Section 7.1 Example: 20, 10, 15) --}}
                            <td class="py-3 text-center">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    @if($canManageStock)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-purple rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1"
                                            onclick="openOpeningStockModal({{ $dish->id }}, '{{ addslashes($dish->name) }}', {{ $openingBaseline !== null ? $openingBaseline : 'null' }})"
                                            title="Click to set or update opening baseline"
                                        >
                                            <span class="fw-bold fs-6">{{ $openingBaseline !== null ? $openingBaseline : 0 }}</span>
                                            <span class="small" style="font-size: 0.72rem;">portions</span>
                                            <i class="bi bi-pencil-square ms-0.5" style="font-size: 0.7rem;"></i>
                                        </button>
                                        <span class="text-secondary small mt-1" style="font-size: 0.68rem;">Baseline Quantity</span>
                                    @else
                                        <div class="fw-bold fs-6 text-purple">
                                            {{ $openingBaseline !== null ? $openingBaseline : 0 }} <span class="text-secondary fw-normal small" style="font-size: 0.72rem;">portions</span>
                                        </div>
                                        <span class="text-secondary small mt-0.5" style="font-size: 0.7rem;">Baseline Quantity</span>
                                    @endif
                                </div>
                            </td>

                            {{-- 3. Stock Added (Section 7.1 Example: 30, 10, 20) --}}
                            <td class="py-3 text-center">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <div class="d-flex align-items-center gap-1.5">
                                        @if($todayAdded > 0)
                                            <span class="badge rounded-pill bg-emerald-subtle text-emerald border border-emerald-subtle px-2.5 py-1 fw-bold fs-6">
                                                +{{ $todayAdded }}
                                            </span>
                                        @else
                                            <span class="text-muted fw-semibold">0</span>
                                        @endif
                                        <span class="text-secondary small" style="font-size: 0.72rem;">portions</span>
                                    </div>
                                    <span class="text-secondary small mt-0.5" style="font-size: 0.68rem;">Added Today</span>
                                </div>
                            </td>

                            {{-- 4. Available Stock (Live Balance) --}}
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
                                    {{-- Quick Stock In (+) --}}
                                    @if($canManageStock && $isTracked)
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-dashcart-emerald d-inline-flex align-items-center justify-content-center"
                                            onclick="openStockInModalSingle({{ $dish->id }}, '{{ addslashes($dish->name) }}', {{ (int)($dish->prepared_stock_quantity ?? 0) }})"
                                            title="Quick Stock In (+)"
                                        >
                                            <i class="bi bi-plus-lg me-1"></i>Stock In
                                        </button>

                                        {{-- Adjust (Wastage / Corrections) --}}
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-dashcart-light d-inline-flex align-items-center justify-content-center"
                                            onclick="openAdjustModal({{ $dish->id }}, '{{ addslashes($dish->name) }}', {{ (int)($dish->prepared_stock_quantity ?? 0) }})"
                                            title="Log Wastage, Damaged, or Adjustment"
                                        >
                                            <i class="bi bi-sliders me-1"></i>Adjust
                                        </button>
                                    @endif

                                    {{-- History Modal --}}
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
}

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

.text-purple {
    color: #7c3aed !important;
}

.btn-outline-purple {
    color: #7c3aed;
    border: 1px solid #ddd6fe;
    background-color: #f5f3ff;
    transition: all 0.15s ease;
}
.btn-outline-purple:hover {
    color: #ffffff;
    background-color: #7c3aed;
    border-color: #7c3aed;
}

.bg-emerald-subtle { background-color: #ecfdf5; }
.text-emerald { color: #10b981; }
.bg-amber-subtle { background-color: #fffbeb; }
.text-amber { color: #f59e0b; }

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
