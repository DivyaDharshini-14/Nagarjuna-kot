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

    <form method="GET" class="row g-2 mb-4 p-3 bg-white border rounded-4 shadow-sm align-items-center">
        <div class="col-md-3">
            <select name="kitchen_id" class="form-select rounded-3">
                <option value="">All Kitchens</option>
                @foreach($kitchens as $k)
                    <option value="{{ $k->id }}" @selected((string) request('kitchen_id') === (string) $k->id)>{{ $k->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select rounded-3">
                <option value="">All Statuses</option>
                @foreach(['new', 'accepted', 'preparing', 'ready', 'served', 'cancelled'] as $st)
                    <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" name="date" value="{{ request('date') }}" class="form-control rounded-3" title="Filter by date">
        </div>
        <div class="col-md-auto ms-auto d-flex gap-2">
            <button class="btn btn-primary rounded-3 px-3 fw-semibold">Filter</button>
            <a href="{{ route('kots.index') }}" class="btn btn-outline-secondary rounded-3 px-3">Reset</a>
        </div>
    </form>

    <div class="row g-4">
        @forelse($kots as $kot)
            @php
                $now = now();
                $receivedAt = $kot->order?->created_at ?? $kot->created_at ?? $now;
                $startTime = $kot->accepted_at ?? $kot->created_at ?? $now;

                if ($kot->ticket_status === 'ready') {
                    $elapsedSeconds = $kot->duration_seconds ?? ($kot->ready_at ? abs((int) $kot->ready_at->diffInSeconds($startTime)) : abs((int) $kot->updated_at->diffInSeconds($startTime)));
                } elseif (in_array($kot->ticket_status, ['served', 'cancelled'])) {
                    $elapsedSeconds = $kot->duration_seconds ?? ($kot->ready_at ? abs((int) $kot->ready_at->diffInSeconds($startTime)) : abs((int) $kot->updated_at->diffInSeconds($startTime)));
                } else {
                    $elapsedSeconds = abs((int) $now->diffInSeconds($startTime));
                }

                if ($elapsedSeconds > 43200 && !$kot->duration_seconds) {
                    $elapsedSeconds = $kot->ticket_status === 'ready' ? 720 : 180;
                }

                $h = floor($elapsedSeconds / 3600);
                $m = floor(($elapsedSeconds % 3600) / 60);
                $s = $elapsedSeconds % 60;
                $timeFormatted = $h > 0 ? sprintf('%02d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);

                $timerClass = match($kot->ticket_status) {
                    'new', 'accepted' => 'timer-new',
                    'preparing' => 'timer-prep',
                    'ready' => 'timer-ready',
                    default => 'timer-served',
                };
            @endphp
            <div class="col-md-6 col-lg-4">
                <div class="kot-ref-card">
                    {{-- Top Row: Circular Station Avatar (Left) + Duration Pill (Right) --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="kot-station-avatar" title="{{ $kot->kitchen?->name }}">
                            {{ strtoupper(substr($kot->kitchen?->name ?? 'K', 0, 1)) }}
                        </div>

                        <div class="kot-timer-pill {{ $timerClass }}" title="Waiting Duration">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" class="me-1">
                                <path d="M12 1a1 1 0 0 1 1 1v1.07A8.001 8.001 0 0 1 19.93 11H21a1 1 0 1 1 0 2h-1.07A8.001 8.001 0 0 1 12 21a8.001 8.001 0 0 1-7.93-8H3a1 1 0 1 1 0-2h1.07A8.001 8.001 0 0 1 11 3.07V2a1 1 0 0 1 1-1zm0 4a6 6 0 1 0 6 6 6 6 0 0 0-6-6zm0 2a1 1 0 0 1 1 1v2.586l1.707 1.707a1 1 0 0 1-1.414 1.414l-2-2A1 1 0 0 1 11 10.5V8a1 1 0 0 1 1-1z"/>
                            </svg>
                            <span class="live-card-timer" data-sec="{{ $elapsedSeconds }}">{{ $timeFormatted }}</span>
                        </div>
                    </div>

                    {{-- Metadata Row: Station Name + Order Received Time (Like 'Amazon 5 days ago') --}}
                    <div class="d-flex align-items-baseline gap-2 mb-1">
                        <span class="fw-bold fs-6" style="color: var(--theme-primary, #dc2626);">{{ $kot->kitchen?->name }}</span>
                        <span class="text-muted small">{{ $receivedAt->format('h:i A') }}</span>
                    </div>

                    {{-- Card Title: Prominent KOT Number (Like 'Senior UI/UX Designer') --}}
                    <h4 class="kot-ref-title mb-3 text-truncate" title="{{ $kot->kot_number }}">
                        {{ $kot->kot_number }}
                    </h4>

                    {{-- Pill Tags Row (Like 'Part-time' / 'Senior level') --}}
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="kot-ref-tag">
                            <i class="bi bi-geo-alt me-1 text-muted"></i>{{ $kot->order?->table?->name ? 'Table ' . $kot->order->table->name : 'Takeaway' }}
                        </span>
                        <span class="kot-ref-tag">
                            <i class="bi bi-receipt me-1 text-muted"></i>#{{ $kot->order?->order_number }}
                        </span>
                        <span class="kot-ref-tag tag-status-{{ $kot->ticket_status }}">
                            {{ ucfirst($kot->ticket_status) }}
                        </span>
                    </div>

                    {{-- Items / Dishes List --}}
                    <div class="kot-dishes-box">
                        @foreach($kot->items as $item)
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span class="text-dark fw-semibold small text-truncate pe-2">{{ $item->menuItem?->name }}</span>
                                <span class="badge bg-light text-dark border px-2 py-1 small fw-bold flex-shrink-0">× {{ $item->quantity }}</span>
                            </div>
                            @if($item->special_instructions)
                                <small class="text-danger d-block mb-1 ps-1">
                                    <i class="bi bi-info-circle me-1"></i>{{ $item->special_instructions }}
                                </small>
                            @endif
                        @endforeach
                    </div>

                    {{-- Footer: Waiter Info (Left) + Sleek Solid Black Action Button (Right, Like 'Apply now') --}}
                    <div class="d-flex justify-content-between align-items-center pt-2 mt-auto">
                        <div>
                            <div class="fw-bold text-dark small">{{ $kot->order?->waiter?->name ? 'Waiter: ' . $kot->order->waiter->name : 'Staff' }}</div>
                            <div class="text-muted" style="font-size: 0.76rem;">{{ $kot->items->sum('quantity') }} items</div>
                        </div>

                        <div>
                            <form method="POST" action="{{ route('kots.update-status', $kot) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                @if($kot->ticket_status === 'new')
                                    <input type="hidden" name="status" value="accepted">
                                    <button class="btn kot-ref-btn">Accept</button>
                                @elseif($kot->ticket_status === 'accepted')
                                    <input type="hidden" name="status" value="preparing">
                                    <button class="btn kot-ref-btn">Start Preparing</button>
                                @elseif($kot->ticket_status === 'preparing')
                                    <input type="hidden" name="status" value="ready">
                                    <button class="btn kot-ref-btn">Mark Ready</button>
                                @elseif($kot->ticket_status === 'ready')
                                    <input type="hidden" name="status" value="served">
                                    <button class="btn kot-ref-btn">Mark Served</button>
                                @else
                                    <span class="btn btn-light kot-ref-btn text-muted disabled">Completed</span>
                                @endif
                            </form>
                        </div>
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function formatSecs(seconds) {
            const totalSec = Math.max(0, parseInt(seconds || 0, 10));
            const h = Math.floor(totalSec / 3600);
            const m = Math.floor((totalSec % 3600) / 60);
            const s = totalSec % 60;
            if (h > 0) {
                return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            }
            return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }

        // Live real-time ticking for waiting duration (identical to KDS)
        setInterval(() => {
            document.querySelectorAll('.live-card-timer').forEach(el => {
                let sec = parseInt(el.getAttribute('data-sec') || '0', 10);
                sec++;
                el.setAttribute('data-sec', sec);
                el.innerText = formatSecs(sec);
            });
        }, 1000);
    });
</script>
@endpush

