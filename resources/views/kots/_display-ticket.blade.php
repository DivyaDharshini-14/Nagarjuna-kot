@php
    $terminal = in_array($ticket->ticket_status, ['ready', 'served', 'cancelled'], true);
    $canUpdate = ($interactive ?? true) && ($displayToken || auth()->user()?->hasPermission('kot.edit'));
@endphp
<article class="kot-card status-{{ $ticket->ticket_status }} p-3"
    data-ticket-id="{{ $ticket->id }}" data-ticket-status="{{ $ticket->ticket_status }}"
    data-cancelled-quantity="{{ $ticket->items->sum('cancelled_quantity') }}"
    data-created-at="{{ $ticket->created_at->timestamp }}"
    data-ended-at="{{ $terminal ? ($ticket->ready_at ?? $ticket->updated_at)->timestamp : '' }}">
    <div class="d-flex justify-content-between align-items-start border-bottom border-secondary pb-2 mb-2">
        <div>
            <h4 class="mb-0 fw-bold text-white">{{ $ticket->kot_number }}</h4>
            <span class="text-secondary small">Order: {{ $ticket->order?->order_number }}</span>
            <span class="badge bg-info text-dark fs-6 mt-1">Table: {{ $ticket->order?->table?->name }}</span>
        </div>
        <div class="text-end">
            <span class="badge bg-dark border text-capitalize">{{ $ticket->ticket_status }}</span>
            <div class="text-secondary small mt-1">Received {{ $ticket->created_at->format('H:i:s') }}</div>
            <div class="text-warning small mt-1">{{ $terminal ? 'Duration' : 'Waiting' }} <span class="waiting-duration">--</span></div>
        </div>
    </div>
    @if($ticket->order?->special_instructions)
        <p class="text-warning small">{{ $ticket->order->special_instructions }}</p>
    @endif
    <ul class="list-unstyled mb-3">
        @foreach($ticket->items as $dish)
            @php
                $remainingQuantity = max(0, $dish->quantity - $dish->cancelled_quantity);
            @endphp
            <li class="d-flex justify-content-between align-items-start py-2 border-bottom border-dark">
                <div>
                    <div class="fs-5 fw-bold {{ $remainingQuantity === 0 ? 'text-secondary text-decoration-line-through' : 'text-light' }}">{{ $dish->menuItem?->name }}</div>
                    @if($dish->special_instructions)
                        <div class="badge bg-danger text-wrap text-start mt-1">Note: {{ $dish->special_instructions }}</div>
                    @endif
                    @if($dish->cancelled_quantity > 0)
                        <div class="badge bg-secondary mt-1">Cancelled: {{ $dish->cancelled_quantity }}</div>
                    @endif
                    <small class="d-block text-secondary text-capitalize">{{ $remainingQuantity === 0 ? 'cancelled' : $dish->item_status }}</small>
                </div>
                <span class="badge bg-light text-dark fs-4 ms-2 px-3 py-1">{{ $remainingQuantity }}</span>
            </li>
        @endforeach
    </ul>
    @if($canUpdate && in_array($ticket->ticket_status, ['new', 'accepted', 'preparing', 'ready'], true))
        @php
            $nextStatus = ['new' => 'accepted', 'accepted' => 'preparing', 'preparing' => 'ready', 'ready' => 'served'][$ticket->ticket_status];
            $nextLabel = ['accepted' => 'Accept ticket', 'preparing' => 'Start preparing', 'ready' => 'Mark ready', 'served' => 'Dispatch / served'][$nextStatus];
        @endphp
        <form method="POST" action="{{ $displayToken ? route('kds.registered-status', [$displayToken, $ticket]) : route('kots.update-status', $ticket) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $nextStatus }}">
            <button class="btn btn-success w-100 fw-bold py-2">{{ $nextLabel }}</button>
        </form>
    @endif
</article>
