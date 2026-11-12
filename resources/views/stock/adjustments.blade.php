@extends('layouts.app')

@section('title', 'Stock Adjustment - Kitchen Production, Wastage & Corrections')

@section('content')
<div class="dashcart-stock-page">
    {{-- Alerts --}}
    @include('partials.alerts')

    {{-- Dashcart Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1" style="letter-spacing: -0.02em; font-size: 1.65rem;">
                Stock Adjustment
            </h1>
            <p class="text-secondary small mb-0" style="font-size: 0.85rem;">
                Record prepared dish production, log service wastage, report spoiled dishes, and reconcile audit differences.
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

    {{-- Dashcart 4 Compact KPI Cards --}}
    <div class="row g-3 mb-4">
        {{-- Stock In Today --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Stock In Today</span>
                        <div class="dashcart-icon-circle bg-emerald-subtle text-emerald">
                            <i class="bi bi-box-arrow-in-down"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number text-emerald">
                        +{{ number_format($todayStats['stock_in_portions'] ?? 0) }}
                    </div>
                </div>
                <div class="pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        Fresh kitchen batches cooked today
                    </span>
                </div>
            </div>
        </div>

        {{-- Wastage Today --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Wastage Logged</span>
                        <div class="dashcart-icon-circle bg-rose-subtle text-rose">
                            <i class="bi bi-trash3"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number text-rose">
                        -{{ number_format($todayStats['wastage_portions'] ?? 0) }}
                    </div>
                </div>
                <div class="pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        Portions expired / unsold
                    </span>
                </div>
            </div>
        </div>

        {{-- Damaged / Spoiled Today --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Damaged / Spoiled</span>
                        <div class="dashcart-icon-circle bg-amber-subtle text-amber">
                            <i class="bi bi-exclamation-octagon"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number text-amber">
                        -{{ number_format($todayStats['damaged_portions'] ?? 0) }}
                    </div>
                </div>
                <div class="pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        Service drops / spillages reported
                    </span>
                </div>
            </div>
        </div>

        {{-- Manual Corrections --}}
        <div class="col-6 col-lg-3">
            <div class="card p-3.5 h-100 border-0 shadow-sm dashcart-card d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="dashcart-stat-label">Manual Corrections</span>
                        <div class="dashcart-icon-circle bg-sky-subtle text-sky">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>
                    <div class="dashcart-stat-number text-sky">
                        {{ number_format($todayStats['reconciled_count'] ?? 0) }}
                    </div>
                </div>
                <div class="pt-2.5 border-top border-light-subtle mt-2">
                    <span class="text-secondary small" style="font-size: 0.75rem;">
                        Physical inventory count audits
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Adjustment Grid: Form on Left (5 cols) & Recent Adjustments on Right (7 cols) --}}
    <div class="row g-4 mb-4">
        {{-- Left: Adjustment Entry Workspace --}}
        <div class="col-12 col-lg-5">
            <div class="card p-4 border-0 shadow-sm dashcart-card h-100">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="dashcart-icon-circle bg-emerald-subtle text-emerald">
                        <i class="bi bi-sliders"></i>
                    </span>
                    <div>
                        <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem;">
                            Record Stock Adjustment
                        </h2>
                        <span class="text-secondary small" style="font-size: 0.78rem;">Kitchen Stock In, Wastage, Damaged &amp; Manual Audit</span>
                    </div>
                </div>

                @if(!$canManageStock)
                    <div class="alert alert-warning small p-3 rounded-3 mb-0">
                        <i class="bi bi-lock me-1"></i>You have read-only access. Stock adjustment permission is required.
                    </div>
                @else
                    <form id="directAdjustForm" method="POST" action="">
                        @csrf

                        {{-- Kitchen Station Selection --}}
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Kitchen Station <span class="text-danger">*</span>
                            </label>
                            <select id="directKitchenSelect" class="form-select form-select-sm" onchange="filterDishesByKitchen(this.value)">
                                <option value="">All Kitchen Stations</option>
                                @foreach($kitchens as $k)
                                    <option value="{{ $k->id }}">{{ $k->name }} ({{ $k->branch?->name ?? 'Main' }})</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Dish Selection --}}
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Prepared Dish <span class="text-danger">*</span>
                            </label>
                            <select id="directDishSelect" class="form-select form-select-sm" required onchange="onDirectDishSelected(this)">
                                <option value="">-- Select Prepared Dish --</option>
                                @foreach($dishes as $d)
                                    @php
                                        $dKitchenIds = $d->kitchenMenuItems->pluck('kitchen_id')->join(',');
                                        $dKitchenNames = $d->kitchenMenuItems->pluck('kitchen.name')->join(', ');
                                    @endphp
                                    <option
                                        value="{{ $d->id }}"
                                        data-stock="{{ (int)$d->prepared_stock_quantity }}"
                                        data-name="{{ $d->name }}"
                                        data-kitchens="{{ $dKitchenIds }}"
                                        data-kitchen-names="{{ $dKitchenNames ?: 'General' }}"
                                    >
                                        {{ $d->name }} ({{ $d->code ?? 'NO-CODE' }}) &bull; Ready: {{ $d->prepared_stock_quantity ?? 0 }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="d-flex justify-content-between align-items-center mt-1.5 px-1">
                                <span class="text-secondary small" style="font-size: 0.75rem;">Current Ready Portions:</span>
                                <span class="badge rounded-pill bg-light text-dark border px-2.5 py-1 fw-bold" id="directDishCurrentStockBadge">
                                    Select dish
                                </span>
                            </div>
                        </div>

                        {{-- Adjustment Action Type --}}
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Adjustment Action <span class="text-danger">*</span>
                            </label>
                            <select name="type" id="directTypeSelect" class="form-select form-select-sm" required onchange="handleDirectTypeChange(this.value)">
                                <option value="stock_in">🟢 Stock In (Kitchen Production Batch +)</option>
                                <option value="wastage">🔴 Record Wastage (Unsold / Expired Portions -)</option>
                                <option value="damaged">🟠 Record Damaged / Spoiled (Dropped Plate / Spill -)</option>
                                <option value="manual_adjustment">🔵 Manual Correction (Physical Audit Difference ±)</option>
                            </select>
                        </div>

                        {{-- Reconciliation Direction Toggle --}}
                        <div class="mb-3 d-none" id="directDirectionGroup">
                            <label class="form-label small fw-semibold text-dark mb-1">Audit Direction <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="direction" id="directDirInc" value="increase" checked>
                                <label class="btn btn-outline-success btn-sm fw-semibold" for="directDirInc">
                                    <i class="bi bi-plus-circle me-1"></i>Count Found Higher (+)
                                </label>

                                <input type="radio" class="btn-check" name="direction" id="directDirDec" value="decrease">
                                <label class="btn btn-outline-danger btn-sm fw-semibold" for="directDirDec">
                                    <i class="bi bi-dash-circle me-1"></i>Count Found Lower (-)
                                </label>
                            </div>
                        </div>

                        {{-- Quantity --}}
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Portion Quantity <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="quantity" id="directQuantityInput" class="form-control" min="1" max="99999" placeholder="e.g. 15" required>
                                <span class="input-group-text bg-light text-secondary">portions</span>
                            </div>
                        </div>

                        {{-- Mandatory Reason --}}
                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-dark mb-1">
                                Mandatory Operational Reason <span class="text-danger">*</span>
                            </label>
                            <textarea name="reason" id="directReasonInput" class="form-control form-control-sm" rows="3" placeholder="Provide staff operational reason (e.g. Fresh batch cooked for dinner rush, or burnt portion discarded)" required minlength="3"></textarea>
                        </div>

                        <button type="submit" id="directSubmitBtn" class="btn btn-primary w-100 py-2 rounded-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" disabled>
                            <i class="bi bi-check2-circle fs-5"></i>
                            <span>Record Stock Adjustment</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Right: Today's Recent Adjustments Table --}}
        <div class="col-12 col-lg-7">
            <div class="card p-0 border-0 shadow-sm dashcart-card h-100 overflow-hidden">
                <div class="p-3.5 px-4 bg-white border-bottom d-flex align-items-center justify-content-between" style="border-color: #f1f5f9 !important;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-secondary"></i>
                        <h2 class="fs-6 fw-bold text-dark mb-0" style="font-size: 1.05rem;">
                            Recent Adjustments Today
                        </h2>
                    </div>
                    <a href="{{ route('stock.history') }}" class="small text-emerald fw-semibold text-decoration-none">
                        View Full Ledger <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                        <thead class="dashcart-thead">
                            <tr>
                                <th class="ps-4 py-2.5">Time</th>
                                <th class="py-2.5">Dish</th>
                                <th class="py-2.5">Action</th>
                                <th class="py-2.5 text-center">Delta</th>
                                <th class="pe-4 py-2.5">Staff &amp; Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentAdjustments as $adj)
                                <tr>
                                    <td class="ps-4 py-3">
                                        <div class="fw-semibold text-dark">{{ $adj->created_at?->format('h:i A') }}</div>
                                        <div class="text-secondary" style="font-size: 0.72rem;">{{ $adj->created_at?->diffForHumans() }}</div>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-bold text-dark">{{ $adj->menuItem?->name ?? 'Dish' }}</div>
                                        <div class="text-secondary font-monospace" style="font-size: 0.72rem;">{{ $adj->menuItem?->code }}</div>
                                    </td>
                                    <td class="py-3">
                                        @if($adj->transaction_type === 'stock_in' || $adj->transaction_type === 'opening_stock')
                                            <span class="dashcart-pill pill-in">
                                                <span class="pill-dot dot-green"></span> Stock In
                                            </span>
                                        @elseif($adj->transaction_type === 'wastage')
                                            <span class="dashcart-pill pill-out">
                                                <span class="pill-dot dot-red"></span> Wastage
                                            </span>
                                        @elseif($adj->transaction_type === 'damaged')
                                            <span class="dashcart-pill pill-low">
                                                <span class="pill-dot dot-yellow"></span> Damaged
                                            </span>
                                        @else
                                            <span class="dashcart-pill pill-grey">
                                                <span class="pill-dot dot-grey"></span> Correction
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-center">
                                        @if($adj->quantity_delta > 0)
                                            <span class="fw-bold text-emerald">+{{ $adj->quantity_delta }}</span>
                                        @elseif($adj->quantity_delta < 0)
                                            <span class="fw-bold text-rose">{{ $adj->quantity_delta }}</span>
                                        @else
                                            <span class="text-secondary">0</span>
                                        @endif
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ $adj->stock_before }} &rarr; {{ $adj->stock_after }}</div>
                                    </td>
                                    <td class="pe-4 py-3">
                                        <div class="fw-semibold text-dark">{{ $adj->user?->name ?? 'System' }}</div>
                                        <div class="text-secondary text-truncate" style="max-width: 220px; font-size: 0.75rem;" title="{{ $adj->reason }}">
                                            {{ $adj->reason }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-secondary">
                                        <i class="bi bi-clock-history fs-3 text-muted mb-2 d-block"></i>
                                        <p class="mb-0 small">No adjustments recorded yet today.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Shared Stock Modals --}}
    @include('stock.partials.modals')
</div>

<script>
function filterDishesByKitchen(kitchenId) {
    const dishSelect = document.getElementById('directDishSelect');
    const options = dishSelect.querySelectorAll('option');

    dishSelect.value = '';
    onDirectDishSelected(dishSelect);

    options.forEach(opt => {
        if (!opt.value) return;
        if (!kitchenId) {
            opt.style.display = '';
            return;
        }
        const kitchens = (opt.getAttribute('data-kitchens') || '').split(',');
        if (kitchens.includes(kitchenId.toString())) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
}

function onDirectDishSelected(sel) {
    const opt = sel.options[sel.selectedIndex];
    const btn = document.getElementById('directSubmitBtn');
    const badge = document.getElementById('directDishCurrentStockBadge');
    const form = document.getElementById('directAdjustForm');

    if (!sel.value) {
        btn.disabled = true;
        badge.textContent = 'Select dish';
        form.action = '';
        return;
    }

    const stock = opt.getAttribute('data-stock');
    badge.textContent = stock + ' portions';
    form.action = `/stock/${sel.value}/adjust`;
    btn.disabled = false;
}

function handleDirectTypeChange(val) {
    const group = document.getElementById('directDirectionGroup');
    if (!group) return;
    if (val === 'manual_adjustment') {
        group.classList.remove('d-none');
    } else {
        group.classList.add('d-none');
    }
}
</script>
@endsection
