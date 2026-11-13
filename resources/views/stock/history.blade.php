@extends('layouts.app')

@section('title', 'Stock transaction history')

@section('content')
<div class="tabletop-page-wrapper py-2">
    {{-- Breadcrumb / Super-title --}}
    <div class="text-secondary small mb-1" style="font-size: 0.82rem; color: #6b7280;">
        Stock / History
    </div>

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-4">
        <div>
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.75rem; letter-spacing: -0.02em;">
                Stock transaction history
            </h1>
            <p class="text-secondary small mb-0" style="font-size: 0.86rem; color: #6b7280;">
                Audit ledger recording prepared dish inventory transactions.
            </p>
        </div>

        <div class="text-secondary small" style="font-size: 0.82rem;">
            Showing <strong>{{ $transactions->firstItem() ?? 0 }}-{{ $transactions->lastItem() ?? 0 }}</strong> of <strong>{{ $transactions->total() }}</strong> records
        </div>
    </div>

    {{-- Clean Table Inside Bordered White Container --}}
    <div class="tabletop-card p-0 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabletop-table" id="stockHistoryTable">
                <thead>
                    <tr>
                        <th class="ps-4 py-3" style="width: 18%; min-width: 140px;">Date &amp; Time</th>
                        <th class="py-3" style="width: 25%; min-width: 180px;">Dish</th>
                        <th class="py-3" style="width: 20%; min-width: 160px;">Transaction Type</th>
                        <th class="py-3 text-center" style="width: 13%; min-width: 110px;">Quantity</th>
                        <th class="py-3" style="width: 12%; min-width: 110px;">Staff User</th>
                        <th class="pe-4 py-3" style="width: 12%; min-width: 130px;">Reason</th>
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
                            {{-- 1. Date & Time --}}
                            <td class="ps-4 py-3.5">
                                <div class="fw-medium text-dark" style="font-size: 0.86rem;">
                                    {{ $tx->created_at?->format('d M Y, h:i A') }}
                                </div>
                                <div class="text-muted small" style="font-size: 0.72rem;">
                                    {{ $tx->created_at?->diffForHumans() }}
                                </div>
                            </td>

                            {{-- 2. Dish --}}
                            <td class="py-3.5">
                                <span class="fw-medium text-dark" style="font-size: 0.88rem;">
                                    {{ $dish?->name ?? 'Dish' }}
                                </span>
                            </td>

                            {{-- 3. Transaction Type (Exactly 6 Approved Types) --}}
                            <td class="py-3.5">
                                @if($type === 'opening_stock')
                                    <span class="tabletop-badge badge-opening">
                                        Opening Stock
                                    </span>
                                @elseif($type === 'stock_in')
                                    <span class="tabletop-badge badge-stock-in">
                                        Stock In
                                    </span>
                                @elseif($type === 'order_deduction')
                                    <span class="tabletop-badge badge-order-deduction">
                                        Order Deduction
                                    </span>
                                @elseif($type === 'order_cancellation_reversal' || $type === 'cancellation_reversal')
                                    <span class="tabletop-badge badge-cancellation-reversal">
                                        Order Cancellation Reversal
                                    </span>
                                @elseif($type === 'wastage' || $type === 'damaged')
                                    <span class="tabletop-badge badge-wastage">
                                        Wastage
                                    </span>
                                @elseif($type === 'manual_adjustment')
                                    <span class="tabletop-badge badge-manual-adjustment">
                                        Manual Adjustment
                                    </span>
                                @else
                                    <span class="tabletop-badge badge-default">
                                        {{ ucwords(str_replace('_', ' ', $type)) }}
                                    </span>
                                @endif
                            </td>

                            {{-- 4. Quantity (Clear Visual Indicators for Additions & Deductions) --}}
                            <td class="py-3.5 text-center">
                                @if($delta > 0)
                                    <span class="fw-bold text-success" style="font-size: 0.88rem;">
                                        +{{ $delta }}
                                    </span>
                                    <span class="text-muted small" style="font-size: 0.72rem;">portions</span>
                                @elseif($delta < 0)
                                    <span class="fw-bold text-danger" style="font-size: 0.88rem;">
                                        {{ $delta }}
                                    </span>
                                    <span class="text-muted small" style="font-size: 0.72rem;">portions</span>
                                @else
                                    <span class="text-secondary fw-medium" style="font-size: 0.88rem;">0</span>
                                @endif
                            </td>

                            {{-- 5. Staff User --}}
                            <td class="py-3.5">
                                <span class="text-dark" style="font-size: 0.86rem;">
                                    {{ $tx->user?->name ?? 'System' }}
                                </span>
                            </td>

                            {{-- 6. Reason --}}
                            <td class="pe-4 py-3.5">
                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                    @if($tx->order)
                                        <a href="{{ route('orders.show', $tx->order) }}" class="badge bg-light text-primary border text-decoration-none" style="font-size: 0.72rem;" title="View Order">
                                            #{{ $tx->order->order_number }}
                                        </a>
                                    @endif
                                    <span class="text-secondary small" style="font-size: 0.84rem;">
                                        {{ $tx->reason ?: '—' }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-secondary">
                                <div class="py-4">
                                    <i class="bi bi-clock-history fs-2 text-muted opacity-50 mb-2 d-block"></i>
                                    <div class="fw-semibold text-dark mb-1" style="font-size: 0.95rem;">No records available</div>
                                    <span class="text-muted small">No stock transactions have been recorded yet.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($transactions->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 border-top" style="border-color: #f1f5f9 !important;">
                <span class="text-secondary small">
                    Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }} of {{ $transactions->total() }} records
                </span>
                <div>
                    {{ $transactions->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

<style>
/* Tabletop Card & Table */
.tabletop-card {
    background-color: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.tabletop-table {
    border-collapse: separate;
    border-spacing: 0;
}

.tabletop-table thead th {
    background-color: #f9fafb !important;
    color: #4b5563;
    font-size: 0.8rem;
    font-weight: 600;
    border-top: none;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
    letter-spacing: 0.01em;
}

.tabletop-table tbody td {
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.tabletop-table tbody tr:last-child td {
    border-bottom: none;
}

.tabletop-table tbody tr:hover {
    background-color: #f9fafb !important;
}

/* 6 Transaction Type Badges in Tabletop Style */
.tabletop-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1.35;
    white-space: nowrap;
}

/* 1. Opening Stock (Soft Purple) */
.badge-opening {
    background-color: #f3e8ff;
    color: #6b21a8;
}

/* 2. Stock In (Soft Green) */
.badge-stock-in {
    background-color: #dcfce7;
    color: #15803d;
}

/* 3. Order Deduction (Soft Blue) */
.badge-order-deduction {
    background-color: #e0f2fe;
    color: #0369a1;
}

/* 4. Order Cancellation Reversal (Soft Teal) */
.badge-cancellation-reversal {
    background-color: #ccfbf1;
    color: #0f766e;
}

/* 5. Wastage (Soft Red) */
.badge-wastage {
    background-color: #fee2e2;
    color: #b91c1c;
}

/* 6. Manual Adjustment (Soft Amber) */
.badge-manual-adjustment {
    background-color: #fef3c7;
    color: #b45309;
}

.badge-default {
    background-color: #f3f4f6;
    color: #4b5563;
}
</style>
@endsection
