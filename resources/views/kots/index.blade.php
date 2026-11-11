@extends('layouts.app')

@section('title', 'Kitchen Order Tickets (KOT)')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Kitchen Order Tickets (KOT)</h1>
            <p class="text-muted small mb-0">Live kitchen-wise ticket distribution and preparation progress</p>
        </div>
        <div>
            <a href="{{ route('kds.index') }}" class="btn btn-primary">
                <i class="bi bi-tv me-1"></i>Kitchen Displays (KDS)
            </a>
        </div>
    </div>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="kitchen_id" class="form-select">
                <option value="">All Kitchens</option>
                @foreach($kitchens as $k)
                    <option value="{{ $k->id }}" @selected((string) request('kitchen_id') === (string) $k->id)>{{ $k->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                @foreach(['new', 'accepted', 'preparing', 'ready', 'served', 'cancelled'] as $st)
                    <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="date" value="{{ request('date') }}" class="form-control" title="Filter by date">
        </div>
        <div class="col-md-auto">
            <button class="btn btn-outline-primary">Filter</button>
            <a href="{{ route('kots.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="row g-3">
        @forelse($kots as $kot)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="badge bg-dark">{{ $kot->kot_number }}</span>
                            <strong class="d-block text-primary mt-1">{{ $kot->kitchen?->name }}</strong>
                        </div>
                        <div class="text-end">
                            @php
                                $badgeClass = match($kot->ticket_status) {
                                    'new' => 'bg-danger',
                                    'accepted' => 'bg-info text-dark',
                                    'preparing' => 'bg-warning text-dark',
                                    'ready' => 'bg-success',
                                    'served' => 'bg-secondary',
                                    default => 'bg-light text-dark border',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }} text-capitalize fs-6">{{ $kot->ticket_status }}</span>
                            <div class="text-muted small">{{ $kot->created_at->format('H:i:s') }}</div>
                        </div>
                    </div>

                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between small text-muted mb-2 px-1">
                            <span>Table: <strong class="text-dark">{{ $kot->order?->table?->name }}</strong></span>
                            <span>Order: <a href="{{ route('orders.show', $kot->order_id) }}" class="text-decoration-none">{{ $kot->order?->order_number }}</a></span>
                        </div>

                        <ul class="list-group list-group-flush border rounded mb-2">
                            @foreach($kot->items as $item)
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-2">
                                    <div>
                                        <span class="fw-semibold">{{ $item->menuItem?->name }}</span>
                                        @if($item->special_instructions)
                                            <small class="d-block text-danger">{{ $item->special_instructions }}</small>
                                        @endif
                                    </div>
                                    <span class="badge bg-primary rounded-pill fs-6">{{ $item->quantity }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="card-footer bg-white border-top py-2 d-flex justify-content-between align-items-center">
                        <small class="text-muted">Waiter: {{ $kot->order?->waiter?->name ?? '—' }}</small>

                        <form method="POST" action="{{ route('kots.update-status', $kot) }}" class="d-inline">
                            @csrf
                            @method('PATCH')
                            @if($kot->ticket_status === 'new')
                                <input type="hidden" name="status" value="accepted">
                                <button class="btn btn-sm btn-info text-dark">Accept</button>
                            @elseif($kot->ticket_status === 'accepted')
                                <input type="hidden" name="status" value="preparing">
                                <button class="btn btn-sm btn-warning">Start Preparing</button>
                            @elseif($kot->ticket_status === 'preparing')
                                <input type="hidden" name="status" value="ready">
                                <button class="btn btn-sm btn-success">Mark Ready</button>
                            @elseif($kot->ticket_status === 'ready')
                                <input type="hidden" name="status" value="served">
                                <button class="btn btn-sm btn-secondary">Mark Served</button>
                            @else
                                <span class="badge bg-light text-dark border">Completed</span>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-egg-fried fs-1 d-block mb-2 text-muted"></i>
                No KOT tickets found.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $kots->links() }}
    </div>
@endsection

