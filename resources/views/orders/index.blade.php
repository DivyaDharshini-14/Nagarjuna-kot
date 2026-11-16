@extends('layouts.app')

@section('title', 'Orders')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-0">Orders</h1>
            <p class="text-muted small mb-0">Customer orders and real-time kitchen routing status</p>
        </div>
        @if(auth()->user()->hasPermission('order.add'))
            <a href="{{ route('orders.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-1.5 shadow-sm">
                <i class="bi bi-plus-lg"></i>
                <span>New Order</span>
            </a>
        @endif
    </div>

    <div class="card tabletop-card border-0 shadow-sm mb-3 p-3 bg-white">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px; border-color: #e2e8f0;">
                        <i class="bi bi-search"></i>
                    </span>
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control border-start-0 ps-0"
                        placeholder="Search order #, table, waiter..."
                        style="border-radius: 0 8px 8px 0; border-color: #e2e8f0;"
                    >
                </div>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach(['pending', 'confirmed', 'preparing', 'ready', 'served', 'completed', 'cancelled'] as $st)
                        <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="table_id" class="form-select">
                    <option value="">All tables</option>
                    @foreach($tables as $tbl)
                        <option value="{{ $tbl->id }}" @selected((string) request('table_id') === (string) $tbl->id)>{{ $tbl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date" value="{{ request('date') }}" class="form-control" title="Filter by date">
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-outline-primary">
                    <i class="bi bi-filter me-1"></i>Filter
                </button>
                @if(request()->filled('search') || request()->filled('status') || request()->filled('table_id') || request()->filled('date'))
                    <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="card tabletop-card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table tabletop-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">ORDER #</th>
                        <th>TABLE</th>
                        <th>WAITER</th>
                        <th class="text-center">ITEMS</th>
                        <th class="text-end">TOTAL (₹)</th>
                        <th class="text-center">STATUS</th>
                        <th>KITCHEN KOTS</th>
                        <th>TIME</th>
                        <th class="text-end pe-4" style="width: 80px;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="ps-4">
                                <a href="{{ route('orders.show', $order) }}" class="fw-bold text-dark text-decoration-none">
                                    {{ $order->order_number }}
                                </a>
                                @if($order->customer_name)
                                    <div class="small text-muted">{{ $order->customer_name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-grid-3x3 text-secondary me-1"></i>{{ $order->table?->name ?? 'Takeaway' }}
                                </span>
                            </td>
                            <td>
                                <span class="text-muted">{{ $order->waiter?->name ?? '—' }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-secondary border font-monospace">
                                    {{ $order->items_count }}
                                </span>
                            </td>
                            <td class="text-end fw-semibold text-dark">
                                ₹{{ number_format((float) $order->total_amount, 2) }}
                            </td>
                            <td class="text-center">
                                @php
                                    $statusBadge = match($order->status) {
                                        'pending' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                        'confirmed' => 'bg-primary-subtle text-primary border-primary-subtle',
                                        'preparing' => 'bg-info-subtle text-info-emphasis border-info-subtle',
                                        'ready' => 'bg-success-subtle text-success border-success-subtle',
                                        'served' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                        'completed' => 'bg-success text-white',
                                        'cancelled' => 'bg-danger-subtle text-danger border-danger-subtle',
                                        default => 'bg-light text-dark border',
                                    };
                                @endphp
                                <span class="badge {{ $statusBadge }} rounded-pill px-2.5 py-1">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse(($order->kots ?? $order->kotTickets ?? []) as $kot)
                                        @php
                                            $kotColor = match($kot->ticket_status) {
                                                'ready' => 'badge-success',
                                                'preparing' => 'badge-warning',
                                                'served' => 'badge-secondary',
                                                default => 'badge-info',
                                            };
                                        @endphp
                                        <span class="badge bg-light text-dark border small" title="{{ $kot->kitchen?->name }} ({{ $kot->ticket_status }})">
                                            {{ $kot->kot_number }}
                                        </span>
                                    @empty
                                        <span class="text-muted small">No KOTs</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <small class="text-muted" title="{{ $order->created_at }}">
                                    {{ $order->created_at->format('H:i') }}
                                </small>
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button
                                        type="button"
                                        class="action-dots-btn"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        data-bs-popper-config='{"strategy":"fixed"}'
                                        title="Actions"
                                    >
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end action-dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('orders.show', $order) }}">
                                                <i class="bi bi-eye text-muted"></i>
                                                <span>View Order</span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-receipt fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                No orders found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="card-footer bg-white py-3 px-4 border-top d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $orders->firstItem() }} to {{ $orders->lastItem() }} of {{ $orders->total() }} records
                </div>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
