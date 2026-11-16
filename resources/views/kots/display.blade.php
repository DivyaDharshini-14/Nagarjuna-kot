<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
        .kot-card.status-accepted { border-color: #0dcaf0; }
        .kot-card.status-preparing { border-color: #ffc107; }
        .kot-card.status-ready { border-color: #198754; }
        .kot-card.status-cancelled { border-color: #6c757d; opacity: .82; }
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
            <span id="connection-status" class="badge bg-warning text-dark">Connecting…</span>
            <button id="sound-toggle" type="button" class="btn btn-outline-light btn-sm">Enable sound</button>
            @unless($displayToken)
                <a href="{{ route('kots.index') }}" class="btn btn-outline-light btn-sm">Exit KDS</a>
            @endunless
        </div>
    </header>

    <main class="container-fluid py-4">
        <div class="row g-3">
            @forelse($tickets as $ticket)
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="kot-card status-{{ $ticket->ticket_status }} p-3" data-created-at="{{ $ticket->created_at->timestamp }}">
                        <div class="d-flex justify-content-between align-items-start border-bottom border-secondary pb-2 mb-2">
                            <div>
                                <h4 class="mb-0 fw-bold text-white">{{ $ticket->kot_number }}</h4>
                                <span class="text-secondary small">Order: {{ $ticket->order?->order_number }}</span>
                                <span class="badge bg-info text-dark fs-6 mt-1">Table: {{ $ticket->order?->table?->name }}</span>
                            </div>
                            <div class="text-end">
                                <span class="badge {{ $ticket->ticket_status === 'new' ? 'bg-danger' : ($ticket->ticket_status === 'preparing' ? 'bg-warning text-dark' : 'bg-success') }} fs-6 text-capitalize">
                                    {{ $ticket->ticket_status }}
                                </span>
                                <div class="text-secondary small mt-1">{{ $ticket->created_at->format('H:i:s') }}</div>
                                <div class="text-warning small mt-1">Waiting <span class="waiting-duration">—</span></div>
                            </div>
                        </div>

                        <ul class="list-unstyled mb-3">
                            @foreach($ticket->items as $dish)
                                @php($remainingQuantity = max(0, $dish->quantity - $dish->cancelled_quantity))
                                <li class="d-flex justify-content-between align-items-start py-2 border-bottom border-dark">
                                    <div>
                                        <div class="fs-5 fw-bold {{ $remainingQuantity === 0 ? 'text-secondary text-decoration-line-through' : 'text-light' }}">{{ $dish->menuItem?->name }}</div>
                                        @if($dish->special_instructions)
                                            <div class="badge bg-danger text-wrap text-start mt-1">Note: {{ $dish->special_instructions }}</div>
                                        @endif
                                        @if($dish->cancelled_quantity > 0)
                                            <div class="badge bg-secondary mt-1">Cancelled: {{ $dish->cancelled_quantity }}</div>
                                        @endif
                                    </div>
                                    <span class="badge bg-light text-dark fs-4 ms-2 px-3 py-1">{{ $remainingQuantity }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="d-grid gap-2">
                            <form method="POST" action="{{ $displayToken ? route('kds.registered-status', [$displayToken, $ticket]) : route('kots.update-status', $ticket) }}">
                                @csrf
                                @method('PATCH')
                                @if($ticket->ticket_status === 'new')
                                    <input type="hidden" name="status" value="accepted">
                                    <button class="btn btn-info w-100 fw-bold py-2 fs-6">ACCEPT TICKET</button>
                                @elseif($ticket->ticket_status === 'accepted')
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
        (() => {
            const kitchenId = @json($kitchen->id);
            const key = @json(config('broadcasting.connections.reverb.key'));
            const host = @json(config('broadcasting.connections.reverb.options.host'));
            const port = @json(config('broadcasting.connections.reverb.options.port'));
            const secure = @json(config('broadcasting.connections.reverb.options.scheme')) === 'https';
            const status = document.getElementById('connection-status');
            const soundButton = document.getElementById('sound-toggle');
            const soundStorageKey = `nagarjuna-kds-sound-${kitchenId}`;
            let soundEnabled = localStorage.getItem(soundStorageKey) === 'enabled';
            let socket;
            let reconnectTimer;
            let retry = 0;
            let connected = false;
            let connectedOnce = false;

            const setStatus = (text, ok = false) => {
                status.textContent = text;
                status.className = `badge ${ok ? 'bg-success' : 'bg-warning text-dark'}`;
            };
            const updateDurations = () => {
                document.querySelectorAll('[data-created-at]').forEach(card => {
                    const seconds = Math.max(0, Math.floor(Date.now() / 1000) - Number(card.dataset.createdAt));
                    const minutes = Math.floor(seconds / 60);
                    const hours = Math.floor(minutes / 60);
                    const label = hours > 0 ? `${hours}h ${minutes % 60}m` : minutes > 0 ? `${minutes}m` : `${seconds}s`;
                    card.querySelector('.waiting-duration').textContent = label;
                });
            };
            const playAlert = () => {
                if (!soundEnabled) return;
                try {
                    const audio = new AudioContext();
                    const oscillator = audio.createOscillator();
                    const gain = audio.createGain();
                    oscillator.connect(gain);
                    gain.connect(audio.destination);
                    oscillator.frequency.value = 880;
                    gain.gain.value = 0.12;
                    oscillator.start();
                    oscillator.stop(audio.currentTime + 0.28);
                    oscillator.onended = () => audio.close();
                } catch (_) {}
            };
            soundButton.textContent = soundEnabled ? 'Sound enabled' : 'Enable sound';
            soundButton.addEventListener('click', () => {
                soundEnabled = !soundEnabled;
                localStorage.setItem(soundStorageKey, soundEnabled ? 'enabled' : 'disabled');
                soundButton.textContent = soundEnabled ? 'Sound enabled' : 'Enable sound';
                if (soundEnabled) playAlert();
            });

            const decode = value => {
                if (typeof value === 'string') { try { return JSON.parse(value); } catch (_) { return {}; } }
                return value || {};
            };
            const subscribe = async socketId => {
                const channelName = `private-kitchens.${kitchenId}`;
                const authUrl = @json($displayToken ? route('kds.broadcasting.auth', $displayToken) : route('web.broadcasting.auth'));
                const response = await fetch(authUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({socket_id: socketId, channel_name: channelName}),
                });
                if (!response.ok) throw new Error('Kitchen display authorization failed.');
                const authorization = (await response.json()).auth;
                socket.send(JSON.stringify({event: 'pusher:subscribe', data: {auth: authorization, channel: channelName}}));
            };
            const connect = () => {
                if (!key || !host) {
                    setStatus('Realtime unavailable · retrying');
                    setTimeout(() => window.location.reload(), 15000);
                    return;
                }
                setStatus('Connecting to kitchen updates…');
                try {
                    const scheme = secure ? 'wss' : 'ws';
                    socket = new WebSocket(`${scheme}://${host}:${port}/app/${key}?protocol=7&client=js&version=8.4.0&flash=false`);
                    socket.onmessage = async event => {
                        let message;
                        try { message = JSON.parse(event.data); } catch (_) { return; }
                        const data = decode(message.data);
                        if (message.event === 'pusher:connection_established') {
                            try { await subscribe(data.socket_id); }
                            catch (_) { socket.close(); }
                        } else if (message.event === 'pusher:ping') {
                            socket.send(JSON.stringify({event: 'pusher:pong', data: {}}));
                        } else if (message.event === 'pusher_internal:subscription_succeeded' || message.event === 'pusher:subscription_succeeded') {
                            retry = 0;
                            connected = true;
                            setStatus('Live updates connected', true);
                            if (connectedOnce) window.location.reload();
                            connectedOnce = true;
                        } else if (message.event === 'pusher:error') {
                            setStatus('Live updates unavailable · reconnecting');
                            socket.close();
                        } else if (message.event === 'kot.ticket.created' || message.event === 'kot.ticket.updated' || message.event === 'kot.ticket.cancelled') {
                            if (message.event === 'kot.ticket.created' || message.event === 'kot.ticket.cancelled' || data.change_type === 'created' || data.change_type === 'cancelled') playAlert();
                            setTimeout(() => window.location.reload(), 250);
                        }
                    };
                    socket.onerror = () => setStatus('Connection lost · reconnecting');
                    socket.onclose = () => {
                        connected = false;
                        setStatus('Disconnected · reconnecting');
                        clearTimeout(reconnectTimer);
                        const wait = Math.min(15000, 1000 * (2 ** retry++));
                        reconnectTimer = setTimeout(connect, wait);
                    };
                } catch (_) {
                    setStatus('Connection lost · reconnecting');
                    reconnectTimer = setTimeout(connect, Math.min(15000, 1000 * (2 ** retry++)));
                }
            };
            updateDurations();
            setInterval(updateDurations, 1000);
            setInterval(() => {
                if (!connected && navigator.onLine) window.location.reload();
            }, 15000);
            window.addEventListener('online', () => window.location.reload());
            connect();
        })();
    </script>
</body>
</html>

