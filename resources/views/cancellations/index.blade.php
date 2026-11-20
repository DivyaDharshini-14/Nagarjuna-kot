@extends('layouts.app')

@section('title', 'Cancellation History')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Cancellation History</h1>
            <p class="text-muted small mb-0">Complete record and history of cancelled order items and reasons.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill fs-6">
                <i class="bi bi-x-circle me-1"></i>{{ $cancellations->total() }} {{ Str::plural('Record', $cancellations->total()) }} ({{ $totalCancelledQuantity }} Items)
            </span>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-sm mb-4 p-3 bg-white rounded-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px; border-color: #e2e8f0;">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control border-start-0 ps-0"
                        placeholder="Search order #, dish name, staff, reason..."
                        style="border-radius: 0 8px 8px 0; border-color: #e2e8f0;"
                    >
                </div>
            </div>
            <div class="col-md-3">
                <input type="date" name="date" value="{{ $selectedDate ?? request('date', now()->toDateString()) }}" class="form-control rounded-3" title="Filter by date">
            </div>
            <div class="col-md-auto ms-auto d-flex gap-2">
                <button class="btn btn-primary rounded-3 px-3 fw-semibold">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                @if(request()->filled('search') || request()->filled('date'))
                    <a href="{{ route('cancellations.index') }}" class="btn btn-outline-secondary rounded-3 px-3">Today</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Cancellation History Table --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small text-muted" style="font-size: 0.76rem; letter-spacing: 0.05em;">
                    <tr>
                        <th class="ps-3 py-3" style="width: 50px;">#</th>
                        <th class="py-3">Order #</th>
                        <th class="py-3">Table</th>
                        <th class="py-3">Cancelled Item</th>
                        <th class="py-3">Quantity</th>
                        <th class="py-3">Reason</th>
                        <th class="py-3">Requested By</th>
                        <th class="py-3">Date & Time</th>
                        <th class="pe-3 py-3 text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cancellations as $index => $cancellation)
                        <tr>
                            <td class="ps-3 text-muted small">
                                {{ $cancellations->firstItem() + $index }}
                            </td>
                            <td>
                                @if($cancellation->order)
                                    <a href="{{ route('orders.show', $cancellation->order) }}" class="fw-bold text-decoration-none text-primary">
                                        {{ $cancellation->order->order_number }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ $cancellation->order?->table?->name ? 'Table ' . $cancellation->order->table->name : 'Takeaway' }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    {{ $cancellation->orderItem?->menuItem?->name ?? 'Dish' }}
                                </div>
                                @if($cancellation->orderItem?->special_instructions)
                                    <small class="text-muted d-block">{{ $cancellation->orderItem->special_instructions }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 fw-bold fs-6">
                                    {{ $cancellation->quantity }} ×
                                </span>
                            </td>
                            <td>
                                <span class="text-secondary small fst-italic">
                                    {{ $cancellation->reason ?: 'No reason specified' }}
                                </span>
                                @if($cancellation->decision_note)
                                    <div class="small text-muted mt-0.5">Note: {{ $cancellation->decision_note }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark">
                                    {{ $cancellation->requester?->name ?? 'Staff' }}
                                </div>
                                <span class="text-muted" style="font-size: 0.72rem;">
                                    {{ $cancellation->requester?->designation ?? 'Staff' }}
                                </span>
                            </td>
                            <td>
                                <div class="small text-dark fw-medium">
                                    {{ $cancellation->created_at?->format('d M Y') }}
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    {{ $cancellation->created_at?->format('h:i A') }}
                                </div>
                            </td>
                            <td class="pe-3 text-end">
                                @if(in_array(strtolower($cancellation->status), ['approved', 'cancelled']))
                                    <span class="badge bg-danger px-2.5 py-1.5 rounded-pill">
                                        <i class="bi bi-check-circle me-1"></i>Cancelled
                                    </span>
                                @elseif(strtolower($cancellation->status) === 'rejected')
                                    <span class="badge bg-secondary px-2.5 py-1.5 rounded-pill">
                                        Rejected
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark px-2.5 py-1.5 rounded-pill">
                                        {{ ucfirst($cancellation->status) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-clock-history fs-1 d-block mb-2 text-muted"></i>
                                <div>No cancellation history records found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if($cancellations->hasPages())
        <div class="d-flex justify-content-end">
            {{ $cancellations->links() }}
        </div>
    @endif
@endsection
