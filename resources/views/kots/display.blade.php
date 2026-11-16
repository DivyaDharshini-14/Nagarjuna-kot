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
            <span id="count-new" class="badge bg-danger fs-6">{{ $tickets->whereIn('ticket_status', ['new', 'accepted'])->count() }} New</span>
            <span id="count-preparing" class="badge bg-warning text-dark fs-6">{{ $tickets->where('ticket_status', 'preparing')->count() }} Preparing</span>
            <span id="count-ready" class="badge bg-success fs-6">{{ $tickets->where('ticket_status', 'ready')->count() }} Ready</span>
            <span id="connection-status" class="badge bg-warning text-dark">Connecting…</span>
            <button id="sound-toggle" type="button" class="btn btn-outline-light btn-sm">Enable sound</button>
            @unless($displayToken)
                <a href="{{ route('kots.index') }}" class="btn btn-outline-light btn-sm">Exit KDS</a>
            @endunless
        </div>
    </header>

    <main class="container-fluid py-4">
        @if($errors->any())
            <div class="alert alert-warning">{{ $errors->first() }}</div>
        @endif
        <div class="row g-3">
            @foreach(['new' => 'New orders', 'preparing' => 'Preparing orders', 'ready' => 'Ready orders'] as $stage => $label)
                @php($stageTickets = $tickets->filter(fn ($ticket) => $stage === 'new' ? in_array($ticket->ticket_status, ['new', 'accepted'], true) : $ticket->ticket_status === $stage))
                <section class="col-lg-4" aria-label="{{ $label }}">
                    <h2 class="h4 mb-3">{{ $label }} <span class="badge bg-secondary">{{ $stageTickets->count() }}</span></h2>
                    @forelse($stageTickets as $ticket)
                        @include('kots._display-ticket', ['ticket' => $ticket, 'interactive' => true])
                    @empty
                        <p class="text-secondary">No {{ strtolower($label) }}.</p>
                    @endforelse
                </section>
            @endforeach
        </div>
        <section class="mt-4 border-top border-secondary pt-3" aria-label="Ticket history">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">{{ $historyType === 'completed' ? 'Completed orders' : 'Cancelled items' }}</h2>
                <div class="btn-group">
                    <a class="btn btn-outline-light btn-sm" href="{{ request()->fullUrlWithQuery(['history' => 'cancelled', 'history_page' => 1]) }}">Cancelled items</a>
                    <a class="btn btn-outline-light btn-sm" href="{{ request()->fullUrlWithQuery(['history' => 'completed', 'history_page' => 1]) }}">Completed orders</a>
                </div>
            </div>
            <div class="row g-3">
                @forelse($historyTickets as $ticket)
                    <div class="col-md-6 col-xl-4">@include('kots._display-ticket', ['ticket' => $ticket, 'interactive' => false])</div>
                @empty
                    <p class="text-secondary">No history found.</p>
                @endforelse
            </div>
            {{ $historyTickets->links() }}
        </section>
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
            const soundAllowed = @json((bool) app(\App\Services\BranchSettings::class)->forBranch((int) $kitchen->branch_id)['sound_enabled']);
            const soundStorageKey = `nagarjuna-kds-sound-${kitchenId}`;
            let soundEnabled = soundAllowed && localStorage.getItem(soundStorageKey) === 'enabled';
            let socket;
            let reconnectTimer;
            let retry = 0;
            let connected = false;
            let connectedOnce = false;
            let refreshing = false;
            let refreshAgain = false;
            let lastAlertAt = 0;
            const knownTickets = new Map();
            document.querySelectorAll('[data-ticket-id]').forEach(card => knownTickets.set(card.dataset.ticketId, Number(card.dataset.cancelledQuantity)));

            const setStatus = (text, ok = false) => {
                status.textContent = text;
                status.className = `badge ${ok ? 'bg-success' : 'bg-warning text-dark'}`;
            };
            const updateDurations = () => {
                document.querySelectorAll('[data-created-at]').forEach(card => {
                    const end = card.dataset.endedAt ? Number(card.dataset.endedAt) : Math.floor(Date.now() / 1000);
                    const seconds = Math.max(0, end - Number(card.dataset.createdAt));
                    const minutes = Math.floor(seconds / 60);
                    const hours = Math.floor(minutes / 60);
                    const label = hours > 0 ? `${hours}h ${minutes % 60}m` : minutes > 0 ? `${minutes}m` : `${seconds}s`;
                    card.querySelector('.waiting-duration').textContent = label;
                });
            };
            const refreshDisplay = async () => {
                if (refreshing) { refreshAgain = true; return; }
                refreshing = true;
                try {
                    const response = await fetch(window.location.href, {credentials: 'same-origin', headers: {'Accept': 'text/html'}});
                    if (!response.ok || response.redirected) throw new Error('Display authorization or connection unavailable.');
                    const next = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const main = next.querySelector('main');
                    if (!main) throw new Error('Display unavailable.');
                    let shouldAlert = false;
                    main.querySelectorAll('[data-ticket-id]').forEach(card => {
                        const previous = knownTickets.get(card.dataset.ticketId);
                        const cancelled = Number(card.dataset.cancelledQuantity);
                        if ((previous === undefined && card.dataset.ticketStatus === 'new') || (previous !== undefined && cancelled > previous)) shouldAlert = true;
                        knownTickets.set(card.dataset.ticketId, cancelled);
                    });
                    if (shouldAlert) playAlert();
                    document.querySelector('main').replaceWith(main);
                    ['count-new', 'count-preparing', 'count-ready'].forEach(id => {
                        document.getElementById(id).textContent = next.getElementById(id).textContent;
                    });
                    updateDurations();
                    setStatus(connected ? 'Live updates connected' : 'Live reconnecting · polling active', connected);
                } catch (_) { setStatus('Display unavailable · reconnecting'); }
                finally {
                    refreshing = false;
                    if (refreshAgain) { refreshAgain = false; refreshDisplay(); }
                }
            };
            const playAlert = () => {
                if (!soundEnabled) return;
                if (Date.now() - lastAlertAt < 1000) return;
                lastAlertAt = Date.now();
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
            soundButton.disabled = !soundAllowed;
            if (!soundAllowed) soundButton.textContent = 'Sound disabled by branch';
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
                            refreshDisplay();
                            connectedOnce = true;
                        } else if (message.event === 'pusher:error') {
                            setStatus('Live updates unavailable · reconnecting');
                            socket.close();
                        } else if (message.event === 'kot.ticket.created' || message.event === 'kot.ticket.updated' || message.event === 'kot.ticket.cancelled') {
                            if (message.event === 'kot.ticket.created' || message.event === 'kot.ticket.cancelled' || data.change_type === 'created' || data.change_type === 'cancelled') playAlert();
                            refreshDisplay();
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
                if (navigator.onLine && !document.hidden) refreshDisplay();
            }, 15000);
            window.addEventListener('online', () => { refreshDisplay(); if (!socket || socket.readyState > WebSocket.OPEN) connect(); });
            document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshDisplay(); });
            connect();
        })();
    </script>
</body>
</html>

