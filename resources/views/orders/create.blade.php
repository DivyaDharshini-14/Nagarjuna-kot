@extends('layouts.app')

@section('title', 'Create Order')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">New Customer Order</h1>
        <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Orders
        </a>
    </div>

    <form method="POST" action="{{ route('orders.store') }}" id="orderForm">
        @csrf

        @if(!empty($lockCompany))
            <input type="hidden" name="company_id" value="{{ $selectedCompany }}">
        @endif
        @if(!empty($lockBranch))
            <input type="hidden" name="branch_id" value="{{ $selectedBranch }}">
        @endif

        <div class="row g-3">
            {{-- Left column: Order Metadata & Table --}}
            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-info-circle me-1 text-primary"></i> Order Details
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="table_id">Select Table <span class="text-danger">*</span></label>
                            <select name="table_id" id="table_id" class="form-select @error('table_id') is-invalid @enderror" required>
                                <option value="">Select table</option>
                                @foreach($tables as $table)
                                    <option
                                        value="{{ $table->id }}"
                                        @selected(old('table_id', $selectedTableId) == $table->id)
                                    >
                                        {{ $table->name }} (Cap: {{ $table->seating_capacity }}) - {{ $table->table_status }}
                                    </option>
                                @endforeach
                            </select>
                            @error('table_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="guest_count">Guest Count</label>
                            <input type="number" name="guest_count" id="guest_count" min="1" max="100" class="form-control" value="{{ old('guest_count', 2) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="customer_name">Customer Name <small class="text-muted">(Optional)</small></label>
                            <input type="text" name="customer_name" id="customer_name" class="form-control" value="{{ old('customer_name') }}" placeholder="e.g. John Doe">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="customer_phone">Phone Number <small class="text-muted">(Optional)</small></label>
                            <input type="text" name="customer_phone" id="customer_phone" class="form-control" value="{{ old('customer_phone') }}" placeholder="e.g. 9876543210">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="special_instructions">Order Instructions <small class="text-muted">(Optional)</small></label>
                            <textarea name="special_instructions" id="special_instructions" rows="2" class="form-control" placeholder="e.g. Less spicy, serve together">{{ old('special_instructions') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Order Summary Card --}}
                <div class="card shadow-sm sticky-top" style="top: 80px;">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-cart3 me-1 text-primary"></i> Order Summary</span>
                        <span class="badge bg-primary" id="selectedCountBadge">0 items</span>
                    </div>
                    <div class="card-body">
                        <div id="selectedItemsList" class="mb-3" style="max-height: 250px; overflow-y: auto;">
                            <p class="text-muted small text-center my-3">No dishes selected yet.</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2 fs-5 fw-bold">
                            <span>Total:</span>
                            <span class="text-primary" id="orderTotalDisplay">₹0.00</span>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top">
                        <button type="submit" class="btn btn-success w-100 py-2 fw-semibold" id="submitOrderBtn" disabled>
                            <i class="bi bi-check-circle me-1"></i> Submit & Generate KOT
                        </button>
                    </div>
                </div>
            </div>

            {{-- Right column: Dish Selection --}}
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0 fw-bold"><i class="bi bi-cup-hot text-primary me-1"></i> Menu Dishes</h5>
                            </div>
                            <div class="col-md-6">
                                <input type="search" id="dishSearch" class="form-control form-control-sm" placeholder="Search dish name or code...">
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        @error('items')
                            <div class="alert alert-danger py-2 mb-3">{{ $message }}</div>
                        @enderror

                        @forelse($categories as $category)
                            <div class="category-block mb-4">
                                <h6 class="fw-bold text-uppercase border-bottom pb-2 text-primary">
                                    {{ $category->name }}
                                </h6>
                                <div class="row g-2">
                                    @forelse($category->menuItems as $dish)
                                        <div class="col-md-6 dish-card-col" data-name="{{ strtolower($dish->name) }}" data-code="{{ strtolower($dish->code) }}">
                                            <div class="card h-100 border p-2">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <strong class="d-block">{{ $dish->name }}</strong>
                                                        <small class="text-muted">{{ $dish->code }}</small>
                                                    </div>
                                                    <span class="fw-bold text-dark">₹{{ number_format($dish->selling_price, 2) }}</span>
                                                </div>

                                                <div class="d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
                                                    <small class="text-muted">Quantity:</small>
                                                    <div class="input-group input-group-sm" style="width: 130px;">
                                                        <button class="btn btn-outline-secondary qty-minus" type="button" data-id="{{ $dish->id }}">-</button>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max="99"
                                                            value="0"
                                                            class="form-control text-center dish-qty-input"
                                                            data-id="{{ $dish->id }}"
                                                            data-name="{{ $dish->name }}"
                                                            data-price="{{ $dish->selling_price }}"
                                                            id="qty_{{ $dish->id }}"
                                                        >
                                                        <button class="btn btn-outline-secondary qty-plus" type="button" data-id="{{ $dish->id }}">+</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12 text-muted small">No active dishes in this category.</div>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-cup fs-1 d-block mb-2 text-muted"></i>
                                No active categories or dishes available for this branch.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden container for submitted item inputs --}}
        <div id="hiddenItemInputs"></div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const qtyInputs = document.querySelectorAll('.dish-qty-input');
        const selectedItemsList = document.getElementById('selectedItemsList');
        const orderTotalDisplay = document.getElementById('orderTotalDisplay');
        const selectedCountBadge = document.getElementById('selectedCountBadge');
        const submitOrderBtn = document.getElementById('submitOrderBtn');
        const hiddenItemInputs = document.getElementById('hiddenItemInputs');
        const dishSearch = document.getElementById('dishSearch');

        function updateSummary() {
            let total = 0;
            let totalCount = 0;
            let html = '';
            let hiddenHtml = '';
            let itemIndex = 0;

            qtyInputs.forEach(input => {
                const qty = parseInt(input.value) || 0;
                if (qty > 0) {
                    const id = input.dataset.id;
                    const name = input.dataset.name;
                    const price = parseFloat(input.dataset.price) || 0;
                    const lineTotal = price * qty;

                    total += lineTotal;
                    totalCount += qty;

                    html += `
                        <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                            <div>
                                <span class="fw-semibold">${name}</span>
                                <div class="text-muted">₹${price.toFixed(2)} x ${qty}</div>
                            </div>
                            <span class="fw-bold">₹${lineTotal.toFixed(2)}</span>
                        </div>
                    `;

                    hiddenHtml += `
                        <input type="hidden" name="items[${itemIndex}][menu_item_id]" value="${id}">
                        <input type="hidden" name="items[${itemIndex}][quantity]" value="${qty}">
                    `;
                    itemIndex++;
                }
            });

            if (totalCount === 0) {
                selectedItemsList.innerHTML = '<p class="text-muted small text-center my-3">No dishes selected yet.</p>';
                submitOrderBtn.disabled = true;
            } else {
                selectedItemsList.innerHTML = html;
                submitOrderBtn.disabled = false;
            }

            orderTotalDisplay.textContent = '₹' + total.toFixed(2);
            selectedCountBadge.textContent = totalCount + (totalCount === 1 ? ' item' : ' items');
            hiddenItemInputs.innerHTML = hiddenHtml;
        }

        document.querySelectorAll('.qty-plus').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const input = document.getElementById('qty_' + id);
                input.value = (parseInt(input.value) || 0) + 1;
                updateSummary();
            });
        });

        document.querySelectorAll('.qty-minus').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = this.dataset.id;
                const input = document.getElementById('qty_' + id);
                const current = parseInt(input.value) || 0;
                if (current > 0) {
                    input.value = current - 1;
                    updateSummary();
                }
            });
        });

        qtyInputs.forEach(input => {
            input.addEventListener('change', updateSummary);
            input.addEventListener('keyup', updateSummary);
        });

        if (dishSearch) {
            dishSearch.addEventListener('input', function () {
                const q = this.value.toLowerCase().trim();
                document.querySelectorAll('.dish-card-col').forEach(col => {
                    const name = col.dataset.name;
                    const code = col.dataset.code;
                    if (name.includes(q) || code.includes(q)) {
                        col.style.display = '';
                    } else {
                        col.style.display = 'none';
                    }
                });
            });
        }
    });
    </script>
@endsection

