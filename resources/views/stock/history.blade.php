@extends('layouts.app')

@section('title', 'Stock Transaction History')

@section('content')
<div class="tabletop-page-wrapper py-2">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-4">
        <div>
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.75rem; letter-spacing: -0.02em;">
                Stock Transaction History
            </h1>
         
        </div>

        <div class="text-secondary small" style="font-size: 0.82rem;">
            Showing <strong>{{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }}</strong> of <strong>{{ $transactions->total() }}</strong> records
        </div>
    </div>

    {{-- Table Card (Matching Tabletop Reference UI) --}}
    <div class="card tabletop-card border-0 shadow-sm overflow-hidden mb-4 bg-white" style="border-radius: 14px;">
        {{-- Single Transaction Type Filter (Replaces Individual Filter Pills) --}}
        <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <form method="GET" action="{{ route('stock.history') }}" class="d-flex align-items-center m-0">
                <div class="d-flex align-items-center gap-2">
                    <label for="transactionTypeSelect" class="text-secondary small fw-medium text-nowrap">
                        <i class="bi bi-funnel me-1 text-danger"></i>
                    </label>
                    <select 
                        id="transactionTypeSelect"
                        name="transaction_type" 
                        class="form-select form-select-sm" 
                        style="min-width: 210px; border-radius: 8px; border-color: #cbd5e1; font-size: 0.84rem; font-weight: 500; color: #1e293b; padding: 6px 12px;"
                        onchange="this.form.submit()"
                    >
                        <option value="all" {{ empty(request('transaction_type')) || request('transaction_type') === 'all' ? 'selected' : '' }}>All Transaction Types</option>
                        <option value="stock_in" {{ request('transaction_type') === 'stock_in' ? 'selected' : '' }}>Stock In</option>
                        <option value="opening_stock" {{ request('transaction_type') === 'opening_stock' ? 'selected' : '' }}>Opening Stock</option>
                        <option value="order_deduction" {{ request('transaction_type') === 'order_deduction' ? 'selected' : '' }}>Order Deduction</option>
                        <option value="wastage" {{ request('transaction_type') === 'wastage' ? 'selected' : '' }}>Wastage</option>
                        <option value="manual_adjustment" {{ request('transaction_type') === 'manual_adjustment' ? 'selected' : '' }}>Manual Adjustment</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabletop-table" id="stockHistoryTable">
                <thead>
                    <tr>
                        <th class="ps-4 py-3" style="width: 70px;">S.No</th>
                        <th class="py-3" style="width: 25%;">Dish Name</th>
                        <th class="py-3" style="width: 15%;">Date</th>
                        <th class="py-3" style="width: 13%;">Time</th>
                        <th class="py-3" style="width: 21%;">Transaction Type</th>
                        <th class="py-3 text-center" style="width: 13%;">Qty</th>
                        <th class="pe-4 py-3" style="width: 13%;">User</th>
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
                            {{-- S.No --}}
                            <td class="ps-4 py-3.5 text-muted fw-semibold" style="font-size: 0.86rem;">
                                {{ $loop->iteration + ($transactions->currentPage() - 1) * $transactions->perPage() }}
                            </td>

                            {{-- 1. Dish Name --}}
                            <td class="py-3.5">
                                <span class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                    {{ $dish?->name ?? 'Dish' }}
                                </span>
                            </td>

                            {{-- 2. Date --}}
                            <td class="py-3.5 text-dark fw-medium" style="font-size: 0.86rem;">
                                {{ $tx->created_at?->format('d M Y') }}
                            </td>

                            {{-- 3. Time --}}
                            <td class="py-3.5 text-secondary" style="font-size: 0.86rem;">
                                {{ $tx->created_at?->format('h:i A') }}
                            </td>

                            {{-- 4. Transaction Type (Tabletop Dot Badge matching Reference) --}}
                            <td class="py-3.5">
                                @if($type === 'opening_stock')
                                    <span class="tabletop-dot-badge badge-opening">
                                        <span class="dot"></span> Opening Stock
                                    </span>
                                @elseif($type === 'stock_in')
                                    <span class="tabletop-dot-badge badge-stock-in">
                                        <span class="dot"></span> Stock In
                                    </span>
                                @elseif($type === 'order_deduction')
                                    <span class="tabletop-dot-badge badge-order-deduction">
                                        <span class="dot"></span> Order Deduction
                                    </span>
                                @elseif($type === 'order_cancellation_reversal' || $type === 'cancellation_reversal')
                                    <span class="tabletop-dot-badge badge-cancellation-reversal">
                                        <span class="dot"></span> Cancellation Reversal
                                    </span>
                                @elseif($type === 'wastage' || $type === 'damaged')
                                    <span class="tabletop-dot-badge badge-wastage">
                                        <span class="dot"></span> Wastage
                                    </span>
                                @elseif($type === 'manual_adjustment')
                                    <span class="tabletop-dot-badge badge-manual-adjustment">
                                        <span class="dot"></span> Manual Adjustment
                                    </span>
                                @else
                                    <span class="tabletop-dot-badge badge-default">
                                        <span class="dot"></span> {{ ucwords(str_replace('_', ' ', $type)) }}
                                    </span>
                                @endif
                            </td>

                            {{-- 5. Qty (Clear Visual Indicator) --}}
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

                            {{-- 6. User --}}
                            <td class="pe-4 py-3.5">
                                <span class="text-dark fw-medium" style="font-size: 0.86rem;">
                                    {{ $tx->user?->name ?? 'System' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-secondary">
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
@endsection
