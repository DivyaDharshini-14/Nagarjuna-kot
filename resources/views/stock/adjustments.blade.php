@extends('layouts.app')

@section('title', 'Stock adjustment')

@section('content')
<div class="tabletop-form-container ms-0 py-2">
    {{-- Complete Form Inside White Card Container --}}
    <div class="tabletop-card">
        {{-- Breadcrumb / Super-title --}}
    
        {{-- Page Title & Subtitle --}}
        <h1 class="fw-bold text-dark mb-1" style="font-size: 1.75rem; letter-spacing: -0.02em;">
            Stock adjustment
        </h1>

        @if(!$canManageStock)
            <div class="alert alert-warning small p-3 rounded-3 mb-0">
                <i class="bi bi-lock me-1"></i>You have read-only access. Stock adjustment permission is required to submit adjustments.
            </div>
        @else
            <form id="directAdjustForm" method="POST" action="" onsubmit="return handleFormSubmit(event)">
                @csrf

                {{-- Hidden inputs for backend submission --}}
                <input type="hidden" name="type" id="hiddenType" value="">
                <input type="hidden" name="direction" id="hiddenDirection" value="">

                {{-- Row 1: Dish & Quantity (Two columns) --}}
                <div class="row g-3.5 mb-3.5">
                    {{-- Dish --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-dark mb-1.5" style="font-size: 0.85rem;">
                            Dish
                        </label>
                        <select id="directDishSelect" class="form-select tabletop-input" required onchange="onDirectDishSelected(this)">
                            <option value="">Select dish</option>
                            @foreach($dishes as $d)
                                @php
                                    $dKitchenIds = $d->kitchenMenuItems->pluck('kitchen_id')->join(',');
                                @endphp
                                <option
                                    value="{{ $d->id }}"
                                    data-stock="{{ (int)$d->prepared_stock_quantity }}"
                                    data-name="{{ $d->name }}"
                                    data-kitchens="{{ $dKitchenIds }}"
                                >
                                    {{ $d->name }} (Available: {{ (int)($d->prepared_stock_quantity ?? 0) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Quantity --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-dark mb-1.5" style="font-size: 0.85rem;">
                            Quantity
                        </label>
                        <input
                            type="number"
                            name="quantity"
                            id="directQuantityInput"
                            class="form-control tabletop-input"
                            min="1"
                            max="99999"
                            placeholder="0"
                            required
                            oninput="validateFormReady()"
                        >
                    </div>
                </div>

                {{-- Row 2: Action & Kitchen / Direction (Two columns) --}}
                <div class="row g-3.5 mb-3.5">
                    {{-- Action / Status --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-dark mb-1.5" style="font-size: 0.85rem;">
                            Action
                        </label>
                        <select id="adjustmentActionSelect" class="form-select tabletop-input" required onchange="handleActionTypeChange(this.value)">
                            <option value="">Select action</option>
                            <option value="add_stock">Add Stock (+)</option>
                            <option value="reduce_stock">Reduce Stock (-)</option>
                            <option value="wastage">Record Wastage (-)</option>
                            <option value="damaged">Record Damaged / Spoiled (-)</option>
                            <option value="physical_correction">Physical Stock Correction (&plusmn;)</option>
                        </select>
                    </div>

                    {{-- Column 2: Kitchen Filter or Audit Direction --}}
                    <div class="col-12 col-md-6">
                        {{-- Direction for Physical Correction --}}
                        <div id="auditDirectionContainer" class="d-none">
                            <label class="form-label fw-semibold text-dark mb-1.5" style="font-size: 0.85rem;">
                                Direction
                            </label>
                            <select id="auditDirectionSelect" class="form-select tabletop-input" onchange="updateHiddenFields()">
                                <option value="increase">Actual Count Higher (+)</option>
                                <option value="decrease">Actual Count Lower (-)</option>
                            </select>
                        </div>

                        {{-- Kitchen Station (Filter) --}}
                        <div id="kitchenStationContainer">
                            <label class="form-label fw-semibold text-dark mb-1.5" style="font-size: 0.85rem;">
                                Kitchen
                            </label>
                            <select id="directKitchenSelect" class="form-select tabletop-input" onchange="filterDishesByKitchen(this.value)">
                                <option value="">All kitchens</option>
                                @foreach($kitchens as $k)
                                    <option value="{{ $k->id }}">{{ $k->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Row 3: Notes / Reason (Full Width) --}}
                <div class="mb-3.5">
                    <label class="form-label fw-semibold text-dark mb-1.5" style="font-size: 0.85rem;">
                        Notes
                    </label>
                    <textarea
                        name="reason"
                        id="directReasonInput"
                        class="form-control tabletop-textarea"
                        rows="3"
                        placeholder="Anything your team should know"
                        required
                        minlength="3"
                        maxlength="500"
                        oninput="validateFormReady()"
                    ></textarea>
                </div>

                {{-- Row 4: Bottom Right Buttons (Cancel & Save) --}}
                <div class="d-flex justify-content-end align-items-center gap-2 mt-4 pt-1">
                    <a href="{{ route('stock.adjustments') }}" class="tabletop-cancel-btn">
                        Cancel
                    </a>
                    <button
                        type="submit"
                        id="directSubmitBtn"
                        class="tabletop-save-btn"
                        disabled
                    >
                        Save adjustment
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

<style>
.tabletop-form-container {
    max-width: 740px;
    width: 100%;
    margin-left: 0 !important;
    margin-right: auto !important;
}

.tabletop-card {
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 2rem 2.25rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.tabletop-input {
    height: 42px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.88rem;
    color: #111827;
    background-color: #ffffff;
    padding: 0.45rem 0.8rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.tabletop-input:focus,
.tabletop-textarea:focus {
    border-color: #111827;
    box-shadow: none;
    outline: none;
}

.tabletop-textarea {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.88rem;
    color: #111827;
    background-color: #ffffff;
    padding: 0.65rem 0.8rem;
    resize: vertical;
    min-height: 95px;
}

.tabletop-cancel-btn {
    color: #4b5563;
    font-weight: 500;
    font-size: 0.88rem;
    text-decoration: none;
    padding: 8px 16px;
    transition: color 0.15s ease;
}

.tabletop-cancel-btn:hover {
    color: #111827;
}

.tabletop-save-btn {
    background-color: #dc2626;
    border: 1px solid #dc2626;
    color: #ffffff;
    font-weight: 600;
    font-size: 0.88rem;
    border-radius: 6px;
    padding: 8px 20px;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.tabletop-save-btn:hover {
    background-color: #b91c1c;
    border-color: #b91c1c;
    color: #ffffff;
}

.tabletop-save-btn:disabled {
    background-color: #fca5a5;
    border-color: #fca5a5;
    cursor: not-allowed;
    opacity: 0.85;
}
</style>

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
    const form = document.getElementById('directAdjustForm');

    if (!sel.value) {
        form.action = '';
    } else {
        form.action = `/stock/${sel.value}/adjust`;
    }
    validateFormReady();
}

function handleActionTypeChange(val) {
    const auditContainer = document.getElementById('auditDirectionContainer');
    const kitchenContainer = document.getElementById('kitchenStationContainer');

    if (val === 'physical_correction') {
        auditContainer.classList.remove('d-none');
        if (kitchenContainer) kitchenContainer.classList.add('d-none');
    } else {
        auditContainer.classList.add('d-none');
        if (kitchenContainer) kitchenContainer.classList.remove('d-none');
    }

    updateHiddenFields();
    validateFormReady();
}

function updateHiddenFields() {
    const actionVal = document.getElementById('adjustmentActionSelect')?.value;
    const hiddenType = document.getElementById('hiddenType');
    const hiddenDir = document.getElementById('hiddenDirection');
    const auditDirSelect = document.getElementById('auditDirectionSelect');

    if (!hiddenType || !hiddenDir) return;

    if (actionVal === 'add_stock') {
        hiddenType.value = 'stock_in';
        hiddenDir.value = '';
    } else if (actionVal === 'reduce_stock') {
        hiddenType.value = 'manual_adjustment';
        hiddenDir.value = 'decrease';
    } else if (actionVal === 'wastage') {
        hiddenType.value = 'wastage';
        hiddenDir.value = '';
    } else if (actionVal === 'damaged') {
        hiddenType.value = 'damaged';
        hiddenDir.value = '';
    } else if (actionVal === 'physical_correction') {
        hiddenType.value = 'manual_adjustment';
        hiddenDir.value = auditDirSelect ? auditDirSelect.value : 'increase';
    } else {
        hiddenType.value = '';
        hiddenDir.value = '';
    }
}

function validateFormReady() {
    const dishVal = document.getElementById('directDishSelect')?.value;
    const actionVal = document.getElementById('adjustmentActionSelect')?.value;
    const qtyVal = parseInt(document.getElementById('directQuantityInput')?.value);
    const reasonVal = document.getElementById('directReasonInput')?.value.trim();
    const submitBtn = document.getElementById('directSubmitBtn');

    if (!submitBtn) return;

    const isReady = dishVal && actionVal && qtyVal && qtyVal > 0 && reasonVal && reasonVal.length >= 3;
    submitBtn.disabled = !isReady;
}

function handleFormSubmit(e) {
    updateHiddenFields();
    const hiddenType = document.getElementById('hiddenType').value;
    if (!hiddenType) {
        alert('Please select an adjustment action.');
        return false;
    }
    return true;
}
</script>
@endsection
