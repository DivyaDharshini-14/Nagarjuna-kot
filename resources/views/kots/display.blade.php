<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $kitchen->name }} – Kitchen Display | Nagarjuna KOT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #121212; color: #f8f9fa; min-height: 100vh; }
        .kot-card {
            background-color: #1e1e1e;
            border: 2px solid #333;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: transform 0.15s ease-in-out;
        }
        .kot-card.status-new { border-color: #dc3545; }
        .kot-card.status-preparing { border-color: #ffc107; }
        .kot-card.status-ready { border-color: #198754; }
        .kds-header { background-color: #000; border-bottom: 2px solid #333; padding: 0.75rem 1.5rem; }
    </style>
</head>
<body>
    <header class="kds-header d-flex justify-content-between align-items-center">
        <div>
            <span class="badge bg-primary fs-6 me-2">{{ $kitchen->code }}</span>
            <span class="fs-4 fw-bold text-white">{{ $kitchen->name }}</span>
            <span class="text-secondary small ms-2">Live Kitchen Display</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-danger fs-6">{{ $tickets->where('ticket_status', 'new')->count() }} New</span>
            <span class="badge bg-warning text-dark fs-6">{{ $tickets->where('ticket_status', 'preparing')->count() }} Preparing</span>
            <span class="badge bg-success fs-6">{{ $tickets->where('ticket_status', 'ready')->count() }} Ready</span>
            <a href="{{ route('kots.index') }}" class="btn btn-outline-light btn-sm">Exit KDS</a>
        </div>
    </header>

    <main class="container-fluid py-4">
        <div class="row g-3">
            @forelse($tickets as $ticket)
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="kot-card status-{{ $ticket->ticket_status }} p-3">
                        <div class="d-flex justify-content-between align-items-start border-bottom border-secondary pb-2 mb-2">
                            <div>
                                <h4 class="mb-0 fw-bold text-white">{{ $ticket->kot_number }}</h4>
                                <span class="badge bg-info text-dark fs-6 mt-1">Table: {{ $ticket->order?->table?->name }}</span>
                            </div>
                            <div class="text-end">
                                <span class="badge {{ $ticket->ticket_status === 'new' ? 'bg-danger' : ($ticket->ticket_status === 'preparing' ? 'bg-warning text-dark' : 'bg-success') }} fs-6 text-capitalize">
                                    {{ $ticket->ticket_status }}
                                </span>
                                <div class="text-secondary small mt-1">{{ $ticket->created_at->format('H:i:s') }}</div>
                            </div>
                        </div>

                        <ul class="list-unstyled mb-3">
                            @foreach($ticket->items as $dish)
                                <li class="d-flex justify-content-between align-items-start py-2 border-bottom border-dark">
                                    <div>
                                        <div class="fs-5 fw-bold text-light">{{ $dish->menuItem?->name }}</div>
                                        @if($dish->special_instructions)
                                            <div class="badge bg-danger text-wrap text-start mt-1">Note: {{ $dish->special_instructions }}</div>
                                        @endif
                                    </div>
                                    <span class="badge bg-light text-dark fs-4 ms-2 px-3 py-1">{{ $dish->quantity }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="d-grid gap-2">
                            <form method="POST" action="{{ route('kots.update-status', $ticket) }}">
                                @csrf
                                @method('PATCH')
                                @if($ticket->ticket_status === 'new')
                                    <input type="hidden" name="status" value="preparing">
                                    <button class="btn btn-warning w-100 fw-bold py-2 fs-6">START PREPARING</button>
                                @elseif($ticket->ticket_status === 'preparing')
                                    <input type="hidden" name="status" value="ready">
                                    <button class="btn btn-success w-100 fw-bold py-2 fs-6">MARK READY</button>
                                @elseif($ticket->ticket_status === 'ready')
                                    <input type="hidden" name="status" value="served">
                                    <button class="btn btn-secondary w-100 fw-bold py-2 fs-6">DISPATCH / SERVED</button>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5 text-secondary">
                    <i class="bi bi-check-circle fs-1 d-block mb-3 text-success"></i>
                    <h3>No pending orders in {{ $kitchen->name }}.</h3>
                    <p>New orders will appear automatically here.</p>
                </div>
            @endforelse
        </div>
    </main>

    <script>
        // Auto-refresh every 15 seconds to pull newest tickets
        setTimeout(function() {
            window.location.reload();
        }, 15000);
    </script>
</body>
</html>

