@extends('layouts.app')

@section('title', 'Cancellation Requests')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Cancellation requests</h1>
            <p class="text-muted mb-0">Review waiter requests before they are sent to the kitchen.</p>
        </div>
        <span class="badge bg-warning text-dark">{{ $requests->count() }} pending</span>
    </div>

    @forelse($requests as $batch => $lines)
        @php($first = $lines->first())
        <section class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $first->order?->order_number }}</strong>
                    <span class="text-muted ms-2">Table {{ $first->order?->table?->name ?? '—' }}</span>
                    <span class="text-muted ms-2">Requested by {{ $first->requester?->name ?? 'Staff' }}</span>
                </div>
                <small class="text-muted">{{ $first->created_at?->format('d M Y, H:i') }}</small>
            </div>
            <div class="card-body">
                <ul class="mb-3">
                    @foreach($lines as $line)
                        <li><strong>{{ $line->quantity }} × {{ $line->orderItem?->menuItem?->name }}</strong> — {{ $line->reason }}</li>
                    @endforeach
                </ul>
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('cancellations.approve', $batch) }}">
                        @csrf
                        <button class="btn btn-danger" type="submit">Approve cancellation</button>
                    </form>
                    <form method="POST" action="{{ route('cancellations.reject', $batch) }}" class="d-flex gap-2">
                        @csrf
                        <input class="form-control" name="decision_note" placeholder="Reason for rejection" required minlength="3" maxlength="500">
                        <button class="btn btn-outline-secondary" type="submit">Reject</button>
                    </form>
                </div>
            </div>
        </section>
    @empty
        <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">No cancellation requests need approval.</div></div>
    @endforelse
@endsection
