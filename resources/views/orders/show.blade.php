@extends('layouts.app')

@section('title', 'Order ' . $order->order_number)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h1 class="h3 mb-0">{{ $order->order_number }}</h1>
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
                <span class="badge {{ $badgeClass }} fs-6">{{ ucfirst($order->status) }}</span>
            </div>
            <p class="text-muted small mb-0">
                Created on {{ $order->created_at->format('M d, Y H:i') }} | Table: <strong>{{ $order->table?->name ?? '—' }}</strong> | Waiter: <strong>{{ $order->waiter?->name ?? '—' }}</strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addItemsModal">
                <i class="bi bi-plus-circle me-1"></i> Add Items
            </button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Status Action
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach(['confirmed', 'preparing', 'ready', 'served', 'completed'] as $st)
                        <li>
                            <form method="POST" action="{{ route('orders.update-status', $order) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $st }}">
                                <button class="dropdown-item" type="submit">Mark as {{ ucfirst($st) }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row g-3">
        {{-- Left Column: Order Items Summary --}}
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-receipt me-1 text-primary"></i> Order Items</span>
                    <span class="badge bg-secondary-subtle text-secondary">{{ $order->items->count() }} items</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Dish</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->menuItem?->name }}</strong>
                                        @if($item->special_instructions)
                                            <div class="small text-muted fst-italic">Note: {{ $item->special_instructions }}</div>
                                        @endif
                                        <div class="small text-muted">{{ $item->menuItem?->category?->name }}</div>
                                    </td>
                                    <td class="text-center fw-bold">{{ $item->quantity }}</td>
                                    <td class="text-end text-muted">₹{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-end fw-semibold">₹{{ number_format($item->total_price, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No items.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light border-top">
                            <tr>
                                <th colspan="3" class="text-end fs-6">Grand Total:</th>
                                <th class="text-end fs-5 text-primary">₹{{ number_format($order->total_amount, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right Column: Kitchen-Wise KOT Tickets --}}
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-egg-fried me-1 text-warning"></i> Kitchen KOT Tickets</span>
                    <span class="badge bg-warning-subtle text-dark border">{{ $order->kotTickets->count() }} tickets</span>
                </div>
                <div class="card-body p-3">
                    @forelse($order->kotTickets as $ticket)
                        <div class="card border mb-3 shadow-sm">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <span class="badge bg-dark me-1">{{ $ticket->kot_number }}</span>
                                    <strong class="text-primary">{{ $ticket->kitchen?->name }}</strong>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    @php
                                        $ticketBadge = match($ticket->ticket_status) {
                                            'new' => 'bg-danger',
                                            'accepted' => 'bg-info text-dark',
                                            'preparing' => 'bg-warning text-dark',
                                            'ready' => 'bg-success',
                                            'served' => 'bg-secondary',
                                            default => 'bg-light text-dark border',
                                        };
                                    @endphp
                                    <span class="badge {{ $ticketBadge }} text-capitalize">{{ $ticket->ticket_status }}</span>

                                    <form method="POST" action="{{ route('kots.update-status', $ticket) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        @if($ticket->ticket_status === 'new')
                                            <input type="hidden" name="status" value="accepted">
                                            <button class="btn btn-sm btn-outline-info">Accept</button>
                                        @elseif($ticket->ticket_status === 'accepted')
                                            <input type="hidden" name="status" value="preparing">
                                            <button class="btn btn-sm btn-outline-warning">Prepare</button>
                                        @elseif($ticket->ticket_status === 'preparing')
                                            <input type="hidden" name="status" value="ready">
                                            <button class="btn btn-sm btn-outline-success">Mark Ready</button>
                                        @elseif($ticket->ticket_status === 'ready')
                                            <input type="hidden" name="status" value="served">
                                            <button class="btn btn-sm btn-outline-secondary">Serve</button>
                                        @endif
                                    </form>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <ul class="list-group list-group-flush">
                                    @foreach($ticket->items as $dishItem)
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-2 py-1">
                                            <div>
                                                <span class="fw-semibold">{{ $dishItem->menuItem?->name }}</span>
                                                @if($dishItem->special_instructions)
                                                    <small class="d-block text-danger">{{ $dishItem->special_instructions }}</small>
                                                @endif
                                            </div>
                                            <span class="badge bg-primary rounded-pill fs-6">{{ $dishItem->quantity }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="card-footer bg-white text-muted small py-1 text-end">
                                Generated: {{ $ticket->created_at->format('H:i:s') }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            No KOT tickets generated yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Add Extra Items Modal --}}
    <div class="modal fade" id="addItemsModal" tabindex="-1" aria-labelledby="addItemsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="{{ route('orders.add-items', $order) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="addItemsModalLabel">Add Additional Dishes to {{ $order->order_number }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                        <p class="text-muted small">Select additional dishes. Newly added dishes will automatically generate new kitchen KOT tickets.</p>
                        @foreach($categories as $cat)
                            <h6 class="fw-bold border-bottom pb-1 text-primary mt-3">{{ $cat->name }}</h6>
                            <div class="row g-2">
                                @foreach($cat->menuItems as $dish)
                                    <div class="col-md-6">
                                        <div class="border rounded p-2 d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $dish->name }}</strong>
                                                <div class="text-muted small">₹{{ number_format($dish->selling_price, 2) }}</div>
                                            </div>
                                            <div class="input-group input-group-sm" style="width: 110px;">
                                                <input type="number" min="0" max="99" value="0" name="extra_items[{{ $dish->id }}]" class="form-control text-center extra-item-input" data-id="{{ $dish->id }}">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        <div id="extraItemsHiddenInputs"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="confirmAddItemsBtn">Confirm & Generate New KOT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalForm = document.querySelector('#addItemsModal form');
        const extraInputs = document.querySelectorAll('.extra-item-input');
        const hiddenContainer = document.getElementById('extraItemsHiddenInputs');

        modalForm.addEventListener('submit', function (e) {
            let index = 0;
            let count = 0;
            hiddenContainer.innerHTML = '';

            extraInputs.forEach(input => {
                const qty = parseInt(input.value) || 0;
                if (qty > 0) {
                    const id = input.dataset.id;
                    hiddenContainer.innerHTML += `
                        <input type="hidden" name="items[${index}][menu_item_id]" value="${id}">
                        <input type="hidden" name="items[${index}][quantity]" value="${qty}">
                    `;
                    index++;
                    count++;
                }
            });

            if (count === 0) {
                e.preventDefault();
                alert('Please select at least 1 dish to add.');
            }
        });
    });
    </script>
@endsection

