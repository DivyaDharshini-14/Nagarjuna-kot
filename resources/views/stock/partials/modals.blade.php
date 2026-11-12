{{-- Dashcart Stock Modals: Adjustment, Availability, and History --}}

{{-- 1. Adjust Stock Modal --}}
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <form id="adjustStockForm" method="POST" action="">
                @csrf
                <div class="modal-header border-bottom py-3 px-4" style="border-color: #f1f5f9 !important;">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="dashcart-icon-circle bg-emerald-subtle text-emerald">
                            <i class="bi bi-sliders"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="adjustStockModalLabel" style="font-size: 1.05rem;">
                                Adjust Prepared Stock
                            </h5>
                            <span class="text-secondary small" id="adjustDishName" style="font-size: 0.78rem;"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    {{-- Current Balance Box --}}
                    <div class="p-3 rounded-3 mb-3 d-flex align-items-center justify-content-between" style="background-color: #f8fafc; border: 1px solid #eef2f6;">
                        <span class="text-secondary small fw-medium">Current Ready Stock:</span>
                        <span class="badge rounded-pill px-3 py-1.5 fs-6 fw-bold" id="adjustCurrentStockBadge" style="background-color: #10b981; color: #ffffff;">
                            0 portions
                        </span>
                    </div>

                    {{-- Action Type --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">Adjustment Action <span class="text-danger">*</span></label>
                        <select name="type" id="adjustTypeSelect" class="form-select form-select-sm" required onchange="handleAdjustTypeChange(this.value)">
                            <option value="stock_in">🟢 Stock In (Kitchen Production Batch +)</option>
                            <option value="wastage">🔴 Log Wastage (Unused / Expired Portions -)</option>
                            <option value="damaged">🟠 Log Damaged (Spilled / Spoiled -)</option>
                            <option value="manual_adjustment">🔵 Physical Audit Reconciliation (±)</option>
                        </select>
                    </div>

                    {{-- Direction for Manual Reconciliation --}}
                    <div class="mb-3 d-none" id="adjustDirectionContainer">
                        <label class="form-label small fw-semibold text-dark mb-1">Reconciliation Direction <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="direction" id="dirIncrease" value="increase" checked>
                            <label class="btn btn-outline-success btn-sm fw-semibold" for="dirIncrease">
                                <i class="bi bi-plus-circle me-1"></i>Count Found Higher (+)
                            </label>

                            <input type="radio" class="btn-check" name="direction" id="dirDecrease" value="decrease">
                            <label class="btn btn-outline-danger btn-sm fw-semibold" for="dirDecrease">
                                <i class="bi bi-dash-circle me-1"></i>Count Found Lower (-)
                            </label>
                        </div>
                    </div>

                    {{-- Quantity --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">Portion Quantity <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="quantity" id="adjustQuantityInput" class="form-control" min="1" max="99999" placeholder="Enter portions..." required>
                            <span class="input-group-text bg-light text-secondary">portions</span>
                        </div>
                    </div>

                    {{-- Reason / Audit Note --}}
                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-dark mb-1">Staff Reason / Justification <span class="text-danger">*</span></label>
                        <textarea name="reason" id="adjustReasonInput" class="form-control form-control-sm" rows="2" placeholder="e.g. Prepared 20 fresh biryani portions for lunch rush" required minlength="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top py-2.5 px-4" style="border-color: #f1f5f9 !important; background-color: #fafaf9;">
                    <button type="button" class="btn btn-sm btn-light border px-3 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm text-white px-4 rounded-pill fw-semibold shadow-sm" style="background-color: #10b981; border-color: #10b981;">
                        <i class="bi bi-check-circle me-1"></i>Save Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 2. Dish Availability Modal --}}
<div class="modal fade" id="availabilityModal" tabindex="-1" aria-labelledby="availabilityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <form id="availabilityForm" method="POST" action="">
                @csrf
                @method('PATCH')
                <div class="modal-header border-bottom py-3 px-4" style="border-color: #f1f5f9 !important;">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="dashcart-icon-circle bg-sky-subtle text-sky">
                            <i class="bi bi-toggle-on"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="availabilityModalLabel" style="font-size: 1.05rem;">
                                Update Dish Availability
                            </h5>
                            <span class="text-secondary small" id="availDishName" style="font-size: 0.78rem;"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <p class="small text-secondary mb-3">
                        <i class="bi bi-broadcast text-emerald me-1"></i>Status changes broadcast immediately to Waiter Mobile App and Kitchen Display TV screens.
                    </p>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-dark mb-1">New Availability State <span class="text-danger">*</span></label>
                        <select name="availability" id="availSelect" class="form-select form-select-sm" required>
                            <option value="available">🟢 Available (Ready &amp; Orderable)</option>
                            <option value="temporarily_unavailable">🟡 Temporarily Unavailable (Short kitchen pause)</option>
                            <option value="sold_out">🔴 Sold Out (86'd / Disabled in Mobile App)</option>
                        </select>
                    </div>

                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-dark mb-1">Reason / Note <span class="text-danger">*</span></label>
                        <textarea name="reason" id="availReasonInput" class="form-control form-control-sm" rows="2" placeholder="e.g. Sold out for dinner service" required minlength="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top py-2.5 px-4" style="border-color: #f1f5f9 !important; background-color: #fafaf9;">
                    <button type="button" class="btn btn-sm btn-light border px-3 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4 rounded-pill fw-semibold shadow-sm">
                        <i class="bi bi-broadcast me-1"></i>Update &amp; Broadcast
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 3. Dish Transaction Ledger Modal --}}
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header border-bottom py-3 px-4" style="border-color: #f1f5f9 !important;">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="dashcart-icon-circle bg-light text-secondary border">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="historyModalLabel" style="font-size: 1.05rem;">
                            Dish Stock Ledger
                        </h5>
                        <span class="text-secondary small" id="historyDishSubtitle" style="font-size: 0.78rem;">Audit trail</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <div id="historyLoading" class="p-5 text-center text-secondary">
                    <div class="spinner-border text-emerald spinner-border-sm mb-2" role="status"></div>
                    <div class="small">Loading transaction audit trail...</div>
                </div>

                <div id="historyEmpty" class="p-5 text-center text-secondary d-none">
                    <i class="bi bi-clock-history fs-2 text-muted mb-2 d-block"></i>
                    <p class="mb-0 small">No stock movements recorded for this dish yet.</p>
                </div>

                <div class="table-responsive d-none" id="historyTableContainer">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="dashcart-thead">
                            <tr>
                                <th class="ps-4 py-2.5">Date &amp; Time</th>
                                <th class="py-2.5">Type</th>
                                <th class="py-2.5 text-center">Delta</th>
                                <th class="py-2.5 text-center">Balance</th>
                                <th class="py-2.5">Staff</th>
                                <th class="pe-4 py-2.5">Reference / Reason</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer border-top py-2.5 px-4" style="border-color: #f1f5f9 !important; background-color: #fafaf9;">
                <a href="{{ route('stock.history') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold">
                    <i class="bi bi-box-arrow-up-right me-1"></i>View Full Global Ledger
                </a>
                <button type="button" class="btn btn-sm btn-light border px-3.5 rounded-pill" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Handle adjust type direction toggle
function handleAdjustTypeChange(val) {
    const dirContainer = document.getElementById('adjustDirectionContainer');
    if (!dirContainer) return;
    if (val === 'manual_adjustment') {
        dirContainer.classList.remove('d-none');
    } else {
        dirContainer.classList.add('d-none');
    }
}

// Global modal triggers
window.openAdjustModal = function(id, name, stock) {
    const form = document.getElementById('adjustStockForm');
    form.action = `/stock/${id}/adjust`;
    document.getElementById('adjustDishName').textContent = name;
    document.getElementById('adjustCurrentStockBadge').textContent = (stock ?? 0) + ' portions';
    document.getElementById('adjustQuantityInput').value = '';
    document.getElementById('adjustReasonInput').value = '';
    document.getElementById('adjustTypeSelect').value = 'wastage';
    handleAdjustTypeChange('wastage');
    new bootstrap.Modal(document.getElementById('adjustStockModal')).show();
};

window.openAvailabilityModal = function(id, name, currentAvail) {
    const form = document.getElementById('availabilityForm');
    form.action = `/stock/${id}/availability`;
    document.getElementById('availDishName').textContent = name;
    document.getElementById('availSelect').value = currentAvail;
    document.getElementById('availReasonInput').value = '';
    new bootstrap.Modal(document.getElementById('availabilityModal')).show();
};

window.openHistoryModal = function(id, name) {
    const modalEl = document.getElementById('historyModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('historyDishSubtitle').textContent = name;
    document.getElementById('historyLoading').classList.remove('d-none');
    document.getElementById('historyEmpty').classList.add('d-none');
    document.getElementById('historyTableContainer').classList.add('d-none');
    modal.show();

    fetch(`/stock/${id}/transactions`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('historyLoading').classList.add('d-none');
        if (!data.transactions || data.transactions.length === 0) {
            document.getElementById('historyEmpty').classList.remove('d-none');
            return;
        }

        const tbody = document.getElementById('historyTableBody');
        tbody.innerHTML = '';
        data.transactions.forEach(t => {
            let deltaBadge = '';
            if (t.quantity_delta > 0) {
                deltaBadge = `<span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">+${t.quantity_delta}</span>`;
            } else if (t.quantity_delta < 0) {
                deltaBadge = `<span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle">${t.quantity_delta}</span>`;
            } else {
                deltaBadge = `<span class="badge rounded-pill bg-light text-secondary border">0</span>`;
            }

            let typeBadge = `<span class="dashcart-pill pill-grey">${t.type_label}</span>`;
            if (t.type === 'stock_in' || t.type === 'opening_stock') {
                typeBadge = `<span class="dashcart-pill pill-in"><span class="pill-dot dot-green"></span> ${t.type_label}</span>`;
            } else if (t.type === 'order_deduction') {
                typeBadge = `<span class="dashcart-pill pill-grey" style="color:#0284c7;background:#e0f2fe;border-color:#bae6fd;"><span class="pill-dot" style="background:#0284c7;"></span> ${t.type_label}</span>`;
            } else if (t.type === 'wastage' || t.type === 'damaged') {
                typeBadge = `<span class="dashcart-pill pill-out"><span class="pill-dot dot-red"></span> ${t.type_label}</span>`;
            } else if (t.type === 'order_cancellation_reversal') {
                typeBadge = `<span class="dashcart-pill pill-in" style="color:#0d9488;background:#ccfbf1;border-color:#99f6e4;"><span class="pill-dot" style="background:#0d9488;"></span> ${t.type_label}</span>`;
            }

            tbody.innerHTML += `
                <tr>
                    <td class="ps-4">
                        <div class="fw-semibold text-dark">${t.date}</div>
                        <div class="text-secondary" style="font-size: 0.72rem;">${t.time_ago}</div>
                    </td>
                    <td>${typeBadge}</td>
                    <td class="text-center">${deltaBadge}</td>
                    <td class="text-center fw-bold text-dark">${t.stock_before} &rarr; ${t.stock_after}</td>
                    <td><span class="text-dark fw-medium">${t.user_name}</span></td>
                    <td class="pe-4 text-secondary small">
                        ${t.order_number ? `<span class="badge bg-light text-primary border me-1">#${t.order_number}</span>` : ''}
                        ${t.reason || '—'}
                    </td>
                </tr>
            `;
        });
        document.getElementById('historyTableContainer').classList.remove('d-none');
    })
    .catch(err => {
        document.getElementById('historyLoading').classList.add('d-none');
        document.getElementById('historyEmpty').textContent = 'Failed to load transaction ledger.';
        document.getElementById('historyEmpty').classList.remove('d-none');
    });
};
</script>
