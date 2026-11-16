(() => {
    'use strict';
    const config = window.nagarjunaKds;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character]));
    let historyType = 'cancelled', historyPage = 1, historyLastPage = 1;
    let known = new Map(), loaded = false, feedBusy = false, refreshAgain = false;
    let socket, reconnectTimer, heartbeatTimer, retry = 0, connected = false, feedHealthy = true;
    const subscriptions = new Set();
    let audio, lastAlertAt = 0;
    const soundKey = `nagarjuna-friendly-kds-sound-${config.kitchenId ?? config.branchId ?? 'all'}`;
    let soundEnabled = config.soundAllowed && localStorage.getItem(soundKey) === 'enabled';
    const soundButton = document.getElementById('soundToggle');
    const connection = document.getElementById('networkPill');

    function updateConnection() {
        connection.style.display = 'inline-flex';
        connection.className = `badge px-2 py-1 shadow-sm ${connected && feedHealthy ? 'bg-success' : 'bg-warning text-dark'}`;
        connection.textContent = !feedHealthy ? 'Connection lost · reconnecting' : connected ? 'Live updates connected' : 'Live reconnecting · polling active';
    }
    function sound() {
        if (!soundEnabled) return;
        if (Date.now() - lastAlertAt < 1000) return;
        lastAlertAt = Date.now();
        try {
            audio ??= new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
            const oscillator = audio.createOscillator(), gain = audio.createGain();
            oscillator.frequency.setValueAtTime(587.33, audio.currentTime);
            oscillator.frequency.setValueAtTime(880, audio.currentTime + 0.12);
            gain.gain.setValueAtTime(0.2, audio.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 0.6);
            oscillator.connect(gain); gain.connect(audio.destination);
            oscillator.start(); oscillator.stop(audio.currentTime + 0.6);
        } catch (_) {}
    }
    soundButton.textContent = soundEnabled ? 'Sound enabled' : 'Enable sound';
    soundButton.disabled = !config.soundAllowed;
    if (!config.soundAllowed) soundButton.textContent = 'Sound disabled by branch';
    soundButton.addEventListener('click', () => {
        soundEnabled = !soundEnabled;
        localStorage.setItem(soundKey, soundEnabled ? 'enabled' : 'disabled');
        soundButton.textContent = soundEnabled ? 'Sound enabled' : 'Enable sound';
        sound();
    });
    function duration(value) {
        const seconds = Math.max(0, Number(value) || 0);
        const hours = Math.floor(seconds / 3600), minutes = Math.floor(seconds % 3600 / 60);
        return `${hours ? `${String(hours).padStart(2, '0')}:` : ''}${String(minutes).padStart(2, '0')}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
    }
    function card(ticket, stage, interactive = true) {
        const rawTable = String(ticket.table_name || '').trim();
        const tableName = !rawTable || /n\/a|takeaway/i.test(rawTable) ? 'Takeaway'
            : /^table\s*/i.test(rawTable) ? rawTable : `Table ${rawTable}`;
        const dishes = (ticket.items || []).map(item => `<div class="dish-row-compact">
            <span class="dish-name-compact ${item.quantity === 0 ? 'text-decoration-line-through text-secondary' : ''}">${escape(item.dish_name)}</span>
            <span class="dish-qty-compact">× ${Number(item.quantity)}</span></div>
            ${item.cancelled_quantity ? `<div class="text-danger small fw-bold">Cancelled: ${Number(item.cancelled_quantity)}</div>` : ''}
            ${item.special_instructions ? `<div class="dish-note-pill">${escape(item.special_instructions)}</div>` : ''}`).join('');
        const next = {new: ['accepted', 'Accept'], accepted: ['preparing', 'Start preparing'], preparing: ['ready', 'Mark ready'], ready: ['served', 'Dispatch / served']}[ticket.ticket_status];
        const action = interactive && config.canEdit && next
            ? `<button class="btn btn-sm btn-success mt-2" data-ticket="${Number(ticket.id)}" data-status="${next[0]}">${next[1]}</button>` : '';
        const timerClass = ticket.is_running ? 'live-card-timer' : 'total-duration-val';
        const timerStyle = stage === 'ready' ? 'ready' : stage === 'preparing' ? 'prep' : 'exact';
        return `<article class="kot-order-card" title="${escape(ticket.kot_number)}">
            <div class="kot-badge-box"><span class="kot-box-label">KOT</span><span class="kot-box-number">${escape(ticket.kot_display_number ?? ticket.kot_number)}</span>
                <span class="kot-box-table" title="${escape(tableName)}">${escape(tableName)}</span></div>
            <div class="kot-dishes-middle"><small class="d-block text-secondary">${escape(ticket.order_number)} · ${escape(ticket.kitchen_name)} · ${escape(ticket.ticket_status)}</small>
                ${dishes}${ticket.special_instructions ? `<div class="dish-note-pill mt-1">${escape(ticket.special_instructions)}</div>` : ''}${action}</div>
            <div class="kot-right-column"><div class="timer-capsule-${timerStyle}"><span class="${timerClass}" data-sec="${Number(ticket.elapsed_seconds)}">${duration(ticket.elapsed_seconds)}</span></div>
                <span class="received-time-text">${escape(ticket.order_received_time)}</span></div></article>`;
    }
    function render(id, tickets, stage, empty, interactive = true) {
        document.getElementById(id).innerHTML = tickets.length ? tickets.map(ticket => card(ticket, stage, interactive)).join('') : `<div class="empty-column-state">${empty}</div>`;
    }
    async function pullFeed() {
        if (feedBusy) { refreshAgain = true; return; }
        feedBusy = true;
        try {
            const url = new URL(config.feedUrl, location.origin);
            if (config.branchId) url.searchParams.set('branch_id', config.branchId);
            url.searchParams.set('history', historyType); url.searchParams.set('history_page', historyPage);
            const response = await fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('Kitchen feed unavailable.');
            const data = await response.json();
            const newTickets = data.new_tickets || [], preparing = data.preparing_tickets || [], ready = data.ready_tickets || [], history = data.history_tickets || [];
            let alert = false;
            const snapshot = new Map(known);
            for (const ticket of [...newTickets, ...preparing, ...ready, ...history, ...(data.cancellation_updates || [])]) {
                const cancelled = (ticket.items || []).reduce((sum, item) => sum + Number(item.cancelled_quantity || 0), 0);
                const previous = known.get(ticket.id);
                if (loaded && ((previous === undefined && ['new', 'accepted'].includes(ticket.ticket_status)) || (previous !== undefined && cancelled > previous))) alert = true;
                snapshot.set(ticket.id, cancelled);
            }
            if (alert) sound();
            known = snapshot; loaded = true;
            for (const [name, count] of [['New', data.counts.new], ['Prep', data.counts.preparing], ['Ready', data.counts.ready]]) {
                document.getElementById(`sum${name}`).textContent = count;
                document.getElementById(`count${name}`).textContent = count;
            }
            render('newCardsContainer', newTickets, 'new', 'No new orders waiting');
            render('prepCardsContainer', preparing, 'preparing', 'No orders in preparation');
            render('readyCardsContainer', ready, 'ready', 'No ready orders for pickup');
            render('historyCardsContainer', history, 'history', 'No history found', false);
            historyLastPage = data.history_meta.last_page;
            document.getElementById('historyPage').textContent = `Page ${data.history_meta.page} of ${historyLastPage}`;
            document.getElementById('historyPrevious').disabled = historyPage <= 1;
            document.getElementById('historyNext').disabled = historyPage >= historyLastPage;
            feedHealthy = true;
        } catch (_) { feedHealthy = false; }
        finally {
            feedBusy = false; updateConnection();
            if (refreshAgain) { refreshAgain = false; pullFeed(); }
        }
    }
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-ticket][data-status]');
        if (!button) return;
        button.disabled = true;
        try {
            const response = await fetch(`/kds/tickets/${Number(button.dataset.ticket)}/status`, {
                method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({status: button.dataset.status}),
            });
            if (!response.ok) { const error = await response.json(); throw new Error(error.message || 'Ticket could not be updated.'); }
        } catch (error) { document.getElementById('kdsActionError').textContent = error.message; }
        finally { button.disabled = false; pullFeed(); }
    });
    document.querySelectorAll('[data-history]').forEach(button => button.addEventListener('click', () => {
        historyType = button.dataset.history; historyPage = 1; pullFeed();
    }));
    document.getElementById('historyPrevious').addEventListener('click', () => { if (historyPage > 1) { historyPage--; pullFeed(); } });
    document.getElementById('historyNext').addEventListener('click', () => { if (historyPage < historyLastPage) { historyPage++; pullFeed(); } });
    function decode(value) { if (typeof value !== 'string') return value || {}; try { return JSON.parse(value); } catch (_) { return {}; } }
    function connect() {
        clearTimeout(reconnectTimer);
        if (!navigator.onLine || !config.live.key || !config.live.host || !config.kitchenIds.length) { scheduleReconnect(); return; }
        let current;
        try { current = new WebSocket(`${config.live.secure ? 'wss' : 'ws'}://${config.live.host}:${config.live.port}/app/${encodeURIComponent(config.live.key)}?protocol=7&client=js&version=8.4.0&flash=false`); }
        catch (_) { scheduleReconnect(); updateConnection(); return; }
        socket = current; subscriptions.clear();
        heartbeatTimer = setTimeout(() => current.close(), 10000);
        function heartbeat() { clearTimeout(heartbeatTimer); heartbeatTimer = setTimeout(() => current.close(), 120000); }
        current.onopen = heartbeat;
        current.onmessage = async event => {
            if (socket !== current) return;
            heartbeat();
            const message = decode(event.data), data = decode(message.data);
            if (message.event === 'pusher:connection_established') {
                try {
                    for (const kitchenId of config.kitchenIds) {
                        const channel = `private-kitchens.${kitchenId}`;
                        const response = await fetch(config.authUrl, {method: 'POST', credentials: 'same-origin',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: new URLSearchParams({socket_id: data.socket_id, channel_name: channel})});
                        if (!response.ok) throw new Error('Channel authorization failed.');
                        const authorization = await response.json();
                        if (current.readyState !== WebSocket.OPEN) return;
                        current.send(JSON.stringify({event: 'pusher:subscribe', data: {channel, auth: authorization.auth}}));
                    }
                } catch (_) { current.close(); }
            } else if (message.event === 'pusher:ping') current.send(JSON.stringify({event: 'pusher:pong', data: {}}));
            else if (['pusher_internal:subscription_succeeded', 'pusher:subscription_succeeded'].includes(message.event)) {
                subscriptions.add(message.channel); retry = 0;
                connected = subscriptions.size === config.kitchenIds.length; updateConnection(); pullFeed();
            } else if (message.event === 'pusher:error') current.close();
            else if (String(message.event || '').startsWith('kot.ticket.')) {
                if (data.change_type === 'cancelled') {
                    document.getElementById('kdsActionError').textContent = 'Cancellation received. Review cancelled items.';
                    sound();
                }
                pullFeed();
            }
        };
        current.onerror = () => { connected = false; updateConnection(); current.close(); };
        current.onclose = () => {
            if (socket !== current) return;
            connected = false; clearTimeout(heartbeatTimer); updateConnection(); scheduleReconnect();
        };
    }
    function scheduleReconnect() { clearTimeout(reconnectTimer); reconnectTimer = setTimeout(connect, Math.min(30000, 1000 * 2 ** Math.min(retry++, 5))); }
    setInterval(pullFeed, 12000);
    setInterval(() => {
        document.querySelectorAll('.live-card-timer').forEach(timer => { timer.dataset.sec = Number(timer.dataset.sec) + 1; timer.textContent = duration(timer.dataset.sec); });
        const now = new Date();
        document.getElementById('liveTime').textContent = now.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
        document.getElementById('liveDate').textContent = now.toLocaleDateString([], {weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'});
    }, 1000);
    window.toggleScreenFull = () => document.fullscreenElement ? document.exitFullscreen().catch(() => {}) : document.documentElement.requestFullscreen().catch(() => {});
    window.addEventListener('online', () => { pullFeed(); if (!socket || socket.readyState > WebSocket.OPEN) connect(); });
    window.addEventListener('offline', () => { feedHealthy = false; connected = false; socket?.close(); updateConnection(); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { pullFeed(); if (!socket || socket.readyState > WebSocket.OPEN) connect(); } });
    window.addEventListener('pagehide', () => { clearTimeout(reconnectTimer); clearTimeout(heartbeatTimer); if (socket) { socket.onclose = null; socket.close(); } });
    pullFeed(); connect(); updateConnection();
})();
