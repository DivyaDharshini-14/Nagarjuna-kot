@extends('layouts.app')

@section('title', 'Orders')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Orders</h1>
            <p class="text-muted small mb-0">Customer orders and real-time kitchen routing status</p>
        </div>
        @if(auth()->user()->hasPermission('order.add'))
            <a href="{{ route('orders.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> New Order
            </a>
        @endif
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search order #, table, waiter">
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
        <div class="col-md-auto">
            <button class="btn btn-outline-primary">Filter</button>
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Table</th>
                        <th>Waiter</th>
                        <th class="text-center">Items</th>
                        <th class="text-end">Total (₹)</th>
                        <th>Status</th>
                        <th>Kitchen KOT Tickets</th>
                        <th>Time</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('orders.show', $order) }}" class="fw-bold text-decoration-none">
                                    {{ $order->order_number }}
                                </a>
                                @if($order->customer_name)
                                    <div class="small text-muted">{{ $order->customer_name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-grid-3x3-gap me-1"></i>{{ $order->table?->name ?? '—' }}
                                </span>
                                @if($order->table?->floor)
                                    <small class="d-block text-muted">{{ $order->table->floor->name }}</small>
                                @endif
                            </td>
                            <td>
                                <div>{{ $order->waiter?->name ?? '—' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    {{ $order->items->sum('quantity') }} items
                                </span>
                            </td>
                            <td class="text-end fw-semibold">
                                ₹{{ number_format($order->total_amount, 2) }}
                            </td>
                            <td>
                                @php
                                    $badgeClass = match($order->status) {
                                        'confirmed' => 'bg-primary',
                                        'preparing' => 'bg-warning text-dark',
                                        'ready' => 'bg-info text-dark',
                                        'served' => 'bg-success',
                                        'completed' => 'bg-secondary',
                                        'cancelled' => 'bg-danger',
                                        default => 'bg-light text-dark border',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($order->kotTickets as $kot)
                                        <span class="badge bg-light text-dark border" title="{{ $kot->kot_number }} ({{ $kot->ticket_status }})">
                                            <i class="bi bi-egg-fried text-primary me-1"></i>{{ $kot->kitchen?->name }}:
                                            <span class="text-capitalize fw-normal">{{ $kot->ticket_status }}</span>
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
                            <td class="text-end text-nowrap">
                                <a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-receipt fs-2 d-block mb-2 text-muted"></i>
                                No orders found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $orders->links() }}
    </div>
@endsection

