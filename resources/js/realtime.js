const deviceRoots = () => [...document.querySelectorAll('[data-realtime-device-id]')];
const sidebarCounts = new Map();
const sidebar = document.querySelector('[data-realtime-sidebar-alarm]');
let sidebarTotal = Number(sidebar?.dataset.alarmCount ?? 0);

const number = value => Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function setStatus(root, status) {
    root.dataset.status = status;
    const badge = root.querySelector('[data-realtime-field="connection-status"]');
    if (!badge) return;
    badge.classList.remove('bg-emerald-50', 'text-emerald-700', 'bg-amber-50', 'text-amber-700', 'bg-slate-100', 'text-slate-600');
    const style = status === 'online' ? ['bg-emerald-50', 'text-emerald-700']
        : status === 'pending' ? ['bg-amber-50', 'text-amber-700'] : ['bg-slate-100', 'text-slate-600'];
    badge.classList.add(...style);
    badge.dataset.status = status;
    const label = badge.querySelector('[data-realtime-status-label]');
    if (label) label.textContent = status === 'pending' ? 'Terlambat' : status === 'online' ? 'Online' : 'Offline';
}

function setAlarm(root, count) {
    root.dataset.activeAlarmCount = String(count);
    const label = root.querySelector('[data-realtime-field="alarm-status"]');
    if (!label) return;
    label.classList.remove('text-red-600', 'text-emerald-700', 'text-slate-500');
    label.classList.add(count > 0 ? 'text-red-600' : 'text-emerald-700');
    label.textContent = count > 0 ? `${count} alarm aktif` : 'Tidak ada alarm aktif';
}

function updateSidebar(deviceId, count) {
    const key = String(deviceId);
    if (!sidebarCounts.has(key)) return;
    sidebarTotal += count - sidebarCounts.get(key);
    sidebarCounts.set(key, count);
    document.querySelectorAll('[data-realtime-sidebar-alarm]').forEach(element => {
        element.dataset.alarmCount = String(sidebarTotal);
        element.classList.toggle('hidden', sidebarTotal === 0);
        const label = element.querySelector('[data-realtime-sidebar-alarm-count]');
        if (label) label.textContent = sidebarTotal > 99 ? '99+' : String(sidebarTotal);
    });
    document.querySelectorAll('[data-realtime-sidebar-alarm-empty]').forEach(element => {
        element.classList.toggle('hidden', sidebarTotal > 0);
    });
}

function updateSummary() {
    const cards = [...document.querySelectorAll('.device-card[data-realtime-device-id]')];
    if (!cards.length) return;
    const counts = { online: 0, pending: 0, offline: 0, alarms: 0 };
    for (const card of cards) {
        counts[card.dataset.status] = (counts[card.dataset.status] ?? 0) + 1;
        counts.alarms += Number(card.dataset.activeAlarmCount ?? 0);
    }
    document.querySelectorAll('[data-realtime-summary]').forEach(element => {
        element.textContent = String(counts[element.dataset.realtimeSummary] ?? 0);
    });
}

function updateParameterTiles(root, source) {
    const sourceRoot = [...root.querySelectorAll('[data-source-id]')]
        .find(element => Number(element.dataset.sourceId) === Number(source.source_id));
    if (!sourceRoot) return;
    for (const [key, reading] of Object.entries(source.readings ?? {})) {
        const tile = [...sourceRoot.querySelectorAll('[data-source-parameter]')]
            .find(element => element.dataset.sourceParameter === key);
        if (!tile) continue;
        const value = tile.querySelector('[data-parameter-value]');
        const quality = tile.querySelector('[data-parameter-quality]');
        const time = tile.querySelector('[data-parameter-time]');
        if (value) value.textContent = reading.value == null ? '—' : number(reading.value);
        if (quality) quality.textContent = { good: 'Baik', uncertain: 'Ragu', error: 'Gagal' }[reading.quality] ?? 'Belum ada data';
        if (time) time.textContent = source.recorded_at ? new Date(source.recorded_at).toLocaleTimeString('id-ID') : '';
        tile.classList.remove('border-red-200', 'bg-red-50', 'border-amber-200', 'bg-amber-50', 'border-slate-100', 'bg-slate-50', 'bg-white');
        tile.classList.add(...(reading.quality === 'error' ? ['border-red-200', 'bg-red-50'] : reading.quality === 'uncertain' ? ['border-amber-200', 'bg-amber-50'] : ['border-slate-100', tile.closest('.device-card') ? 'bg-white' : 'bg-slate-50']));
    }
}

function handleParameterUpdate(deviceId, event) {
    for (const root of deviceRoots().filter(element => Number(element.dataset.realtimeDeviceId) === Number(deviceId))) {
        root.dataset.lastSeenAt = new Date().toISOString();
        setStatus(root, 'online');
        const seen = root.querySelector('[data-realtime-field="last-seen"]');
        if (seen) seen.textContent = 'Baru saja';
        if (event.active_alarm_count !== undefined) setAlarm(root, Number(event.active_alarm_count));
        for (const source of event.sources ?? []) updateParameterTiles(root, source);
    }
    if (event.active_alarm_count !== undefined) updateSidebar(deviceId, Number(event.active_alarm_count));
    updateSummary();
    window.dispatchEvent(new CustomEvent('pump:parameters-updated', { detail: event }));
}

function refreshConnectionTimeouts() {
    for (const root of deviceRoots()) {
        if (!root.dataset.lastSeenAt) continue;
        const age = (Date.now() - new Date(root.dataset.lastSeenAt).getTime()) / 1000;
        const interval = Number(root.dataset.sendInterval ?? 10);
        setStatus(root, age <= interval * 9 ? 'online' : age <= interval * 18 ? 'pending' : 'offline');
    }
    updateSummary();
}

function subscribe() {
    if (!window.Echo) return;
    const connection = window.Echo.connector?.pusher?.connection;
    connection?.bind('state_change', ({ current }) => {
        document.querySelectorAll('[data-realtime-connection]').forEach(element => {
            const dot = element.querySelector('[data-realtime-connection-dot]');
            const label = element.querySelector('[data-realtime-connection-label]');
            dot?.classList.remove('bg-emerald-500', 'bg-amber-400', 'bg-gray-400');
            dot?.classList.add(current === 'connected' ? 'bg-emerald-500' : current === 'connecting' ? 'bg-amber-400' : 'bg-gray-400');
            if (label) label.textContent = current === 'connected' ? 'Realtime' : current === 'connecting' ? 'Menghubungkan' : 'Realtime terputus';
        });
    });
    const ids = [...new Set(deviceRoots().map(root => root.dataset.realtimeDeviceId))];
    for (const id of ids) {
        window.Echo.private(`devices.${id}`)
            .listen('.parameters.updated', event => handleParameterUpdate(id, event))
            .error(error => console.error(`Realtime channel devices.${id} failed`, error));
    }
}

for (const root of deviceRoots()) {
    sidebarCounts.set(String(root.dataset.realtimeDeviceId), Number(root.dataset.activeAlarmCount ?? 0));
}
subscribe();
refreshConnectionTimeouts();
window.setInterval(refreshConnectionTimeouts, 10_000);
