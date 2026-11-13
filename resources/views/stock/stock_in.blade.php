@extends('layouts.app')

@section('title', 'Kitchen Stock In (Section 7.1)')

@section('content')
<div class="kitchen-stockin-page">
    {{-- Alerts --}}
    @include('partials.alerts')

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 fw-bold text-dark mb-0" style="letter-spacing: -0.02em; font-size: 1.65rem;">
                    Kitchen Stock In
                </h1>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if(!empty($locksBranch))
                <span class="badge rounded-pill bg-light text-secondary border px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                    <i class="bi bi-geo-alt me-1"></i>{{ auth()->user()->branch?->name ?? 'Assigned Branch' }}
                </span>
            @endif
        </div>
    </div>

    {{-- Prodex Style Table Container --}}
    <div class="card border-0 shadow-sm prodex-card overflow-hidden mb-4">
        {{-- Card Header --}}
        <div class="prodex-card-header px-4 pt-4 pb-3 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
            <div>
                <h2 class="fs-5 fw-bold text-dark mb-0" style="letter-spacing: -0.01em;">
                    All Dishes
                </h2>
            </div>
            <div class="text-secondary small" style="font-size: 0.78rem;">
                Showing <strong>{{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }}</strong> of <strong>{{ $items->total() }}</strong> dishes
            </div>
        </div>

        {{-- Prodex Tabs Bar --}}
        <div class="prodex-tabs d-flex align-items-center gap-4 px-4 border-bottom" style="border-color: #f1f5f9 !important;">
            <span class="prodex-tab active">All</span>
        </div>

        {{-- Prodex Table --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 prodex-table" id="stockInTable">
                <thead>
                    <tr>
                        <th class="ps-4 py-3" style="width: 44px;">
                            <input type="checkbox" class="form-check-input prodex-checkbox" id="checkAllDishes" onchange="toggleSelectAllDishes(this)" title="Select all dishes">
                        </th>
                        <th class="py-3" style="width: 40%; min-width: 220px;">
                            <span class="d-inline-flex align-items-center gap-1">
                                Dish Name
                                <svg class="sort-caret" width="10" height="10" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 3.5l3 3.5H5l3-3.5zm0 9l-3-3.5h6l-3 3.5z"/>
                                </svg>
                            </span>
                        </th>
                        <th class="py-3" style="width: 22%; min-width: 150px;">
                            <span class="d-inline-flex align-items-center gap-1">
                                Opening Stock
                                <svg class="sort-caret" width="10" height="10" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 3.5l3 3.5H5l3-3.5zm0 9l-3-3.5h6l-3 3.5z"/>
                                </svg>
                            </span>
                        </th>
                        <th class="py-3" style="width: 22%; min-width: 150px;">
                            <span class="d-inline-flex align-items-center gap-1">
                                Stock Added
                                <svg class="sort-caret" width="10" height="10" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 3.5l3 3.5H5l3-3.5zm0 9l-3-3.5h6l-3 3.5z"/>
                                </svg>
                            </span>
                        </th>
                        <th class="pe-4 py-3 text-end" style="width: 16%; min-width: 140px;">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $dish)
                        @php
                            $todayAdded = (int) ($todayStockIn[$dish->id] ?? 0);
                            $openingBaseline = isset($todayOpeningStock[$dish->id])
                                ? (int) $todayOpeningStock[$dish->id]
                                : (isset($latestHistoricalOpening[$dish->id]) ? (int) $latestHistoricalOpening[$dish->id] : null);
                            $dishCode = $dish->code ?: sprintf('D%03d', $dish->id);
                        @endphp
                        <tr id="dish-row-{{ $dish->id }}" class="prodex-row" data-dish-id="{{ $dish->id }}" data-dish-name="{{ $dish->name }}">
                            {{-- 1. Checkbox --}}
                            <td class="ps-4 py-3.5">
                                <input type="checkbox" class="form-check-input prodex-checkbox dish-row-checkbox" value="{{ $dish->id }}">
                            </td>

                            {{-- 2. Dish Name (Avatar + Name) --}}
                            <td class="py-3.5">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="prodex-avatar">
                                        {{ strtoupper(substr($dish->name, 0, 2)) }}
                                    </div>
                                    <span class="prodex-dish-name">{{ $dish->name }}</span>
                                </div>
                            </td>

                            {{-- 4. Opening Stock --}}
                            <td class="py-3.5">
                                <div class="d-flex align-items-center gap-1">
                                    <span class="prodex-qty-val" id="opening-stock-display-{{ $dish->id }}">
                                        {{ $openingBaseline !== null ? $openingBaseline : 0 }}
                                    </span>
                                    <span class="prodex-qty-unit">portions</span>
                                </div>
                            </td>

                            {{-- 5. Stock Added (Prodex Status Pill Badge) --}}
                            <td class="py-3.5">
                                @if($todayAdded > 0)
                                    <span
                                        class="prodex-badge prodex-badge-completed"
                                        id="today-added-badge-{{ $dish->id }}"
                                        title="Click to add more prepared stock"
                                        onclick="openStockInModal({{ $dish->id }}, '{{ addslashes($dish->name) }}')"
                                    >
                                        <span id="today-added-num-{{ $dish->id }}">{{ $todayAdded }}</span> portions
                                    </span>
                                @else
                                    <span
                                        class="prodex-badge prodex-badge-pending"
                                        id="today-added-badge-{{ $dish->id }}"
                                        title="Click to add prepared stock"
                                        onclick="openStockInModal({{ $dish->id }}, '{{ addslashes($dish->name) }}')"
                                    >
                                        <span id="today-added-num-{{ $dish->id }}">0</span> portions
                                    </span>
                                @endif
                            </td>

                            {{-- 6. Action (Pencil Edit Baseline + Black Pill Add Stock Button) --}}
                            <td class="pe-4 py-3.5 text-end">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1.5">
                                    @if($canManageStock)
                                        <button
                                            type="button"
                                            class="prodex-icon-btn"
                                            title="Edit Opening Stock Baseline"
                                            onclick="openOpeningStockModal({{ $dish->id }}, '{{ addslashes($dish->name) }}', {{ (int) ($openingBaseline ?? 0) }})"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="prodex-btn-add-stock"
                                            title="Add Prepared Stock"
                                            onclick="openStockInModal({{ $dish->id }}, '{{ addslashes($dish->name) }}')"
                                        >
                                            <i class="bi bi-plus-lg me-1"></i>Add Stock
                                        </button>
                                    @else
                                        <span class="text-muted small">View Only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-5 text-center text-secondary">
                                <i class="bi bi-inbox fs-2 text-muted mb-2 d-block"></i>
                                <div class="fw-semibold text-dark">No dishes found</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($items->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 border-top" style="border-color: #f1f5f9 !important;">
                <span class="text-secondary small">
                    Showing {{ $items->firstItem() }} to {{ $items->lastItem() }} of {{ $items->total() }} dishes
                </span>
                <div>
                    {{ $items->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

{{-- MODAL 1: SET OPENING STOCK (Section 7.1) --}}
<div class="modal fade" id="openingStockModal" tabindex="-1" aria-labelledby="openingStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="dashcart-icon-circle bg-purple-subtle text-purple" style="width: 38px; height: 38px; font-size: 1.1rem;">
                        <i class="bi bi-sunrise"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6" id="openingStockModalLabel">
                            Set Opening Stock Baseline
                        </h5>
                        <span class="text-secondary small" style="font-size: 0.75rem;">Daily morning prep baseline entry (Section 7.1)</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="openingStockForm" onsubmit="handleOpeningStockSubmit(event)">
                <input type="hidden" id="openingDishId" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-light border small mb-3 p-2.5 rounded-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-secondary">Prepared Dish:</span>
                            <span class="fw-bold text-dark" id="openingDishName">—</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            Opening Stock Quantity <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                type="number"
                                class="form-control fw-bold fs-5 text-center"
                                id="openingQuantityInput"
                                min="0"
                                max="99999"
                                placeholder="e.g. 20"
                                required
                            >
                            <span class="input-group-text bg-light text-secondary">portions</span>
                        </div>
                        <span class="text-secondary small mt-1 d-block" style="font-size: 0.72rem;">
                            Sets the available baseline quantity before service starts.
                        </span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            Shift / Prep Note (Optional)
                        </label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="openingReasonInput"
                            placeholder="e.g. Morning preparation baseline"
                            maxlength="250"
                        >
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-light border px-3 rounded-pill" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-sm btn-purple px-4 rounded-pill fw-semibold shadow-sm text-white" id="saveOpeningBtn">
                        <i class="bi bi-check2-circle me-1"></i>Save Opening Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 2: STOCK ADDED POPUP (Section 7.1) --}}
<div class="modal fade" id="stockInModal" tabindex="-1" aria-labelledby="stockInModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="dashcart-icon-circle bg-emerald-subtle text-emerald" style="width: 38px; height: 38px; font-size: 1.1rem;">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0 fs-6" id="stockInModalLabel">
                            Add Prepared Dish Stock
                        </h5>
                        <span class="text-secondary small" style="font-size: 0.75rem;">Enter freshly prepared quantity (Section 7.1)</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="stockInForm" onsubmit="handleStockInSubmit(event)">
                <input type="hidden" id="stockInDishId" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-light border small mb-3 p-2.5 rounded-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-secondary">Prepared Dish:</span>
                            <span class="fw-bold text-dark" id="stockInDishName">—</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            Quantity to Add <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                type="number"
                                class="form-control fw-bold fs-5 text-center"
                                id="stockInQuantityInput"
                                min="1"
                                max="99999"
                                placeholder="e.g. 30"
                                required
                            >
                            <span class="input-group-text bg-light text-secondary">portions</span>
                        </div>
                        <span class="text-secondary small mt-1 d-block" style="font-size: 0.72rem;">
                            Enter the prepared dish quantity ready to be served.
                        </span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">
                            Batch Note (Optional)
                        </label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="stockInReasonInput"
                            placeholder="e.g. Fresh lunch batch"
                            maxlength="250"
                        >
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-light border px-3 rounded-pill" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-sm btn-dark px-4 rounded-pill fw-semibold shadow-sm text-white" id="saveStockInBtn">
                        <i class="bi bi-plus-lg me-1"></i>Add Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- TOAST NOTIFICATION --}}
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="stockInToast" class="toast align-items-center border-0 shadow-lg text-white" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i id="toastIcon" class="bi fs-5"></i>
                <span id="toastMessage" class="fw-semibold small"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<style>
/* Prodex Style Table CSS */
.prodex-card {
    background: #ffffff;
    border: 1px solid #f2f4f7 !important;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.04);
}

.prodex-card-header {
    background: #ffffff;
}

.prodex-tabs {
    padding-bottom: 0;
}

.prodex-tab {
    font-size: 0.85rem;
    font-weight: 500;
    color: #667085;
    padding-bottom: 0.75rem;
    text-decoration: none;
    position: relative;
    cursor: pointer;
}

.prodex-tab.active {
    color: #101828;
    font-weight: 600;
}

.prodex-tab.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background-color: #101828;
    border-radius: 2px 2px 0 0;
}

.prodex-table {
    border-collapse: separate;
    border-spacing: 0;
}

.prodex-table thead th {
    background-color: #f9fafb !important;
    color: #667085;
    font-size: 0.77rem;
    font-weight: 500;
    letter-spacing: 0.01em;
    border-top: 1px solid #f2f4f7;
    border-bottom: 1px solid #eaecf0;
    white-space: nowrap;
}

.sort-caret {
    opacity: 0.35;
    transition: opacity 0.15s ease;
}

.prodex-table thead th:hover .sort-caret {
    opacity: 0.8;
}

.prodex-row {
    transition: background-color 0.15s ease;
}

.prodex-row:hover {
    background-color: #f9fafb !important;
}

.prodex-table tbody td {
    border-bottom: 1px solid #f2f4f7;
    vertical-align: middle;
}

/* Prodex Checkbox */
.prodex-checkbox {
    width: 17px;
    height: 17px;
    border-radius: 4px;
    border: 1.5px solid #d0d5dd;
    cursor: pointer;
    margin: 0;
}

.prodex-checkbox:checked {
    background-color: #101828;
    border-color: #101828;
}

.prodex-checkbox:focus {
    box-shadow: 0 0 0 3px rgba(16, 24, 40, 0.1);
    border-color: #101828;
}

/* Prodex Code like #ORD1006 */
.prodex-code {
    font-size: 0.84rem;
    font-weight: 600;
    color: #101828;
    letter-spacing: -0.01em;
}

/* Prodex Avatar Circle */
.prodex-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #f2f4f7;
    color: #344054;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.78rem;
    flex-shrink: 0;
}

.prodex-dish-name {
    font-size: 0.88rem;
    font-weight: 500;
    color: #101828;
}

.prodex-qty-val {
    font-size: 0.88rem;
    font-weight: 600;
    color: #101828;
}

.prodex-qty-unit {
    font-size: 0.75rem;
    color: #667085;
}

/* Prodex Status Badges (Pills) */
.prodex-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1.35;
    cursor: pointer;
    transition: all 0.15s ease;
}

.prodex-badge:hover {
    filter: brightness(0.96);
}

.prodex-badge-completed {
    background-color: #ecfdf3;
    color: #027a48;
}

.prodex-badge-pending {
    background-color: #fef6ee;
    color: #b54708;
}

/* Prodex Action Buttons */
.prodex-icon-btn {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #667085;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 0.85rem;
}

.prodex-icon-btn:hover {
    background-color: #f2f4f7;
    color: #101828;
}

.prodex-btn-add-stock {
    display: inline-flex;
    align-items: center;
    padding: 4px 11px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    background-color: #101828;
    color: #ffffff;
    border: none;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
    cursor: pointer;
    transition: all 0.15s ease;
}

.prodex-btn-add-stock:hover {
    background-color: #1e293b;
    color: #ffffff;
}

.dashcart-icon-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.text-purple { color: #7c3aed !important; }
.bg-purple-subtle { background-color: #f3e8ff; }
.btn-purple { background-color: #7c3aed; border-color: #7c3aed; }
.btn-purple:hover { background-color: #6d28d9; border-color: #6d28d9; }
.bg-emerald-subtle { background-color: #ecfdf5; }
.text-emerald { color: #10b981; }
</style>

<script>
const CSRF_TOKEN = "{{ csrf_token() }}";

function toggleSelectAllDishes(master) {
    document.querySelectorAll('.dish-row-checkbox').forEach(cb => {
        cb.checked = master.checked;
    });
}

function showToast(type, message) {
    const toastEl = document.getElementById('stockInToast');
    const toastIcon = document.getElementById('toastIcon');
    const toastMsg = document.getElementById('toastMessage');

    toastEl.className = 'toast align-items-center border-0 shadow-lg text-white';
    if (type === 'success') {
        toastEl.classList.add('bg-success');
        toastIcon.className = 'bi bi-check-circle-fill fs-5';
    } else if (type === 'warning') {
        toastEl.classList.add('bg-warning');
        toastEl.classList.remove('text-white');
        toastEl.classList.add('text-dark');
        toastIcon.className = 'bi bi-exclamation-triangle-fill fs-5';
    } else {
        toastEl.classList.add('bg-danger');
        toastIcon.className = 'bi bi-x-circle-fill fs-5';
    }

    toastMsg.textContent = message;
    const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
}

// =========================================================================
// 1. OPENING STOCK MODAL (Section 7.1)
// =========================================================================
let openingModalInstance = null;

function openOpeningStockModal(dishId, dishName, currentOpening) {
    document.getElementById('openingDishId').value = dishId;
    document.getElementById('openingDishName').textContent = dishName;
    const qtyInput = document.getElementById('openingQuantityInput');
    qtyInput.value = currentOpening > 0 ? currentOpening : '';
    document.getElementById('openingReasonInput').value = '';

    if (!openingModalInstance) {
        openingModalInstance = new bootstrap.Modal(document.getElementById('openingStockModal'));
    }
    openingModalInstance.show();
    setTimeout(() => qtyInput.focus(), 300);
}

function handleOpeningStockSubmit(e) {
    e.preventDefault();
    const dishId = document.getElementById('openingDishId').value;
    const qty = parseInt(document.getElementById('openingQuantityInput').value);
    const reason = document.getElementById('openingReasonInput').value;
    const saveBtn = document.getElementById('saveOpeningBtn');

    if (isNaN(qty) || qty < 0) {
        showToast('warning', 'Please enter a valid opening baseline (0 or higher).');
        return;
    }

    const originalBtnText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    fetch(`/stock/${dishId}/opening-stock`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            quantity: qty,
            reason: reason || 'Service opening stock baseline'
        })
    })
    .then(res => res.json().then(data => ({ status: res.status, ok: res.ok, body: data })))
    .then(result => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;

        if (result.ok && result.body.success) {
            const openEl = document.getElementById('opening-stock-display-' + dishId);
            if (openEl) {
                const val = result.body.opening_stock ?? (result.body.data ? result.body.data.opening_stock : qty);
                openEl.textContent = val;
            }
            if (openingModalInstance) {
                openingModalInstance.hide();
            }
            showToast('success', result.body.message || 'Opening baseline updated.');
        } else {
            showToast('danger', result.body.message || 'Failed to update opening stock.');
        }
    })
    .catch(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
        showToast('danger', 'Network error occurred.');
    });
}

// =========================================================================
// 2. STOCK ADDED MODAL (Section 7.1)
// =========================================================================
let stockInModalInstance = null;

function openStockInModal(dishId, dishName) {
    document.getElementById('stockInDishId').value = dishId;
    document.getElementById('stockInDishName').textContent = dishName;
    const qtyInput = document.getElementById('stockInQuantityInput');
    qtyInput.value = '';
    document.getElementById('stockInReasonInput').value = '';

    if (!stockInModalInstance) {
        stockInModalInstance = new bootstrap.Modal(document.getElementById('stockInModal'));
    }
    stockInModalInstance.show();
    setTimeout(() => qtyInput.focus(), 300);
}

function handleStockInSubmit(e) {
    e.preventDefault();
    const dishId = document.getElementById('stockInDishId').value;
    const qty = parseInt(document.getElementById('stockInQuantityInput').value);
    const reason = document.getElementById('stockInReasonInput').value;
    const saveBtn = document.getElementById('saveStockInBtn');

    if (!qty || qty <= 0) {
        showToast('warning', 'Please enter a valid portion quantity (at least 1).');
        return;
    }

    const originalBtnText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';

    fetch(`/stock/${dishId}/stock-in`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            quantity: qty,
            reason: reason || 'Fresh kitchen batch cooked and added to stock'
        })
    })
    .then(res => res.json().then(data => ({ status: res.status, ok: res.ok, body: data })))
    .then(result => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;

        if (result.ok && result.body.success) {
            const numEl = document.getElementById('today-added-num-' + dishId);
            const badgeEl = document.getElementById('today-added-badge-' + dishId);
            const newTotal = result.body.today_stock_in ?? (result.body.data ? result.body.data.today_stock_in : qty);
            
            if (numEl) {
                numEl.textContent = newTotal;
            }
            if (badgeEl && newTotal > 0) {
                badgeEl.className = 'prodex-badge prodex-badge-completed';
            }
            if (stockInModalInstance) {
                stockInModalInstance.hide();
            }
            showToast('success', result.body.message || 'Stock added successfully.');
        } else {
            const err = result.body.message || result.body.error || 'Failed to add stock.';
            showToast('danger', err);
        }
    })
    .catch(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
        showToast('danger', 'Network error occurred while adding stock.');
    });
}
</script>
@endsection
