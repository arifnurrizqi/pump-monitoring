const deviceRoots = () => Array.from(document.querySelectorAll('[data-realtime-device-id]'));

const numberAnimations = new WeakMap();
const levelAnimations = new WeakMap();
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const sidebarAlarmState = {
    total: 0,
    devices: new Map(),
};

const formatNumber = (value, decimals = 2) => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';

    return Number(value).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
};

const displayedNumber = element => {
    const match = element.textContent.replace(/,/g, '').match(/-?\d+(?:\.\d+)?/);

    return match ? Number(match[0]) : Number.NaN;
};

const markUpdated = element => {
    if (reduceMotion) return;

    element.classList.remove('realtime-value-updated');
    void element.offsetWidth;
    element.classList.add('realtime-value-updated');
};

const setElementText = (element, value) => {
    const nextValue = String(value);
    if (element.textContent === nextValue) return;

    element.textContent = nextValue;
    markUpdated(element);
};

const setText = (root, field, value) => {
    root.querySelectorAll(`[data-realtime-field="${field}"]`).forEach(element => {
        setElementText(element, value);
    });
};

function animateNumber(element, target, formatter, duration = 550) {
    const targetNumber = Number(target);
    const previousFrame = numberAnimations.get(element);
    if (previousFrame) window.cancelAnimationFrame(previousFrame);
    numberAnimations.delete(element);

    if (target === null || target === undefined || !Number.isFinite(targetNumber)) {
        delete element.dataset.realtimeNumericValue;
        setElementText(element, '—');
        return;
    }

    const animatedValue = Number(element.dataset.realtimeNumericValue);
    const parsedValue = displayedNumber(element);
    const startValue = Number.isFinite(animatedValue)
        ? animatedValue
        : Number.isFinite(parsedValue) ? parsedValue : targetNumber;
    const finalText = formatter(targetNumber);

    if (reduceMotion || startValue === targetNumber) {
        element.dataset.realtimeNumericValue = String(targetNumber);
        setElementText(element, finalText);
        return;
    }

    const startedAt = performance.now();
    markUpdated(element);

    const render = now => {
        const progress = Math.min((now - startedAt) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = startValue + ((targetNumber - startValue) * eased);

        element.dataset.realtimeNumericValue = String(current);
        element.textContent = formatter(current);

        if (progress < 1) {
            numberAnimations.set(element, window.requestAnimationFrame(render));
            return;
        }

        element.dataset.realtimeNumericValue = String(targetNumber);
        element.textContent = finalText;
        numberAnimations.delete(element);
    };

    numberAnimations.set(element, window.requestAnimationFrame(render));
}

const setNumber = (root, field, value, decimals = 2, unit = '') => {
    root.querySelectorAll(`[data-realtime-field="${field}"]`).forEach(element => {
        animateNumber(
            element,
            value,
            number => unit ? `${formatNumber(number, decimals)} ${unit}` : formatNumber(number, decimals),
        );
    });
};

function initializeSidebarAlarmState() {
    const sidebarAlarm = document.querySelector('[data-realtime-sidebar-alarm]');
    sidebarAlarmState.total = Math.max(0, Number(sidebarAlarm?.dataset.alarmCount ?? 0));

    deviceRoots().forEach(root => {
        sidebarAlarmState.devices.set(
            String(root.dataset.realtimeDeviceId),
            Math.max(0, Number(root.dataset.activeAlarmCount ?? 0)),
        );
    });
}

function renderSidebarAlarmCount() {
    const count = Math.max(0, Math.round(sidebarAlarmState.total));

    document.querySelectorAll('[data-realtime-sidebar-alarm]').forEach(element => {
        const previousCount = Number(element.dataset.alarmCount ?? 0);
        element.dataset.alarmCount = String(count);
        element.title = `${count} active alarm`;
        element.classList.toggle('hidden', count === 0);

        const counter = element.querySelector('[data-realtime-sidebar-alarm-count]');
        if (counter) {
            animateNumber(counter, count, value => value > 99 ? '99+' : formatNumber(value, 0), 400);
        }

        if (previousCount === 0 && count > 0) markUpdated(element);
    });

    document.querySelectorAll('[data-realtime-sidebar-alarm-empty]').forEach(element => {
        element.classList.toggle('hidden', count > 0);
    });
}

function updateSidebarAlarmCount(deviceId, activeAlarmCount) {
    const key = String(deviceId);
    const nextCount = Math.max(0, Number(activeAlarmCount ?? 0));
    const previousCount = sidebarAlarmState.devices.get(key);
    if (previousCount === undefined) return;

    sidebarAlarmState.devices.set(key, nextCount);
    sidebarAlarmState.total = Math.max(0, sidebarAlarmState.total + nextCount - previousCount);
    renderSidebarAlarmCount();
}

const healthIsAttention = status => status === 'warning' || status === 'bad';

function updateHealthColor(root, field, status) {
    root.querySelectorAll(`[data-realtime-field="${field}"]`).forEach(element => {
        element.classList.toggle('text-red-600', healthIsAttention(status));
        element.classList.toggle('text-gray-800', !healthIsAttention(status));
    });
}

function updateConnectionBadge(root, status) {
    const badge = root.querySelector('[data-realtime-field="connection-status"]');
    if (!badge) return;

    const styles = {
        online: ['bg-emerald-100', 'text-emerald-700', 'Online'],
        pending: ['bg-amber-100', 'text-amber-700', 'Pending'],
        offline: ['bg-gray-100', 'text-gray-600', 'Offline'],
    };
    const [background, text, label] = styles[status] ?? styles.offline;

    badge.classList.remove(
        'bg-emerald-100', 'text-emerald-700',
        'bg-amber-100', 'text-amber-700',
        'bg-gray-100', 'text-gray-600',
        'bg-red-100', 'text-red-700',
    );
    badge.classList.add(background, text);
    badge.dataset.status = status;

    const labelElement = badge.querySelector('[data-realtime-status-label]');
    if (labelElement) labelElement.textContent = label;
    else badge.textContent = label;

    const dot = badge.querySelector('[data-realtime-status-dot]');
    if (dot) {
        dot.classList.remove('bg-emerald-500', 'bg-amber-400', 'bg-gray-400', 'bg-red-500');
        dot.classList.add(status === 'online' ? 'bg-emerald-500' : status === 'pending' ? 'bg-amber-400' : 'bg-gray-400');
    }
}

function updateAlarm(root, count) {
    const alarm = root.querySelector('[data-realtime-field="alarm-status"]');
    if (!alarm) return;

    alarm.className = count > 0
        ? 'inline-flex items-center gap-2 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700'
        : 'inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700';
    alarm.innerHTML = count > 0
        ? `<span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-red-600"></span></span>${count} active`
        : '<span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>No active alarm';
}

function updateQuality(root, status) {
    const element = root.querySelector('[data-realtime-field="quality-status"]');
    if (!element) return;

    const states = {
        good: ['text-green-600', 'BAIK'],
        warning: ['text-yellow-600', 'PERLU PERHATIAN'],
        bad: ['text-red-600', 'BURUK'],
        unknown: ['text-gray-500', 'BELUM ADA DATA'],
    };
    const normalizedStatus = Object.hasOwn(states, status) ? status : 'unknown';
    const [color, label] = states[normalizedStatus];
    if (element.dataset.realtimeStatus !== normalizedStatus) markUpdated(element);
    element.dataset.realtimeStatus = normalizedStatus;
    element.classList.remove('text-green-600', 'text-yellow-600', 'text-red-600', 'text-gray-500');
    element.classList.add(color);
    element.querySelectorAll('[data-realtime-quality-icon]').forEach(icon => {
        icon.classList.toggle('hidden', icon.dataset.realtimeQualityIcon !== normalizedStatus);
    });
    const labelElement = element.querySelector('[data-realtime-quality-label]');
    if (labelElement) labelElement.textContent = label;
}

function updateValve(root, state) {
    if (state === null || state === undefined) return;

    const toggle = root.querySelector('.valve-toggle');
    if (!toggle) return;

    toggle.checked = Boolean(state);
    toggle.dataset.original = state ? '1' : '0';
    root.querySelectorAll('.valve-state-label').forEach(label => {
        label.textContent = state ? 'Open' : 'Closed';
    });
}

function updateLevel(root, value) {
    if (value === null || value === undefined) return;

    const level = Math.max(0, Math.min(100, Number(value)));
    setNumber(root, 'level-text', level, 0, '%');

    const fill = root.querySelector('[data-realtime-field="level-fill"]');
    if (!fill) return;

    const previousFrame = levelAnimations.get(fill);
    if (previousFrame) window.cancelAnimationFrame(previousFrame);
    levelAnimations.delete(fill);

    const levelLabel = root.querySelector('[data-realtime-field="level-text"]');
    const displayedLevel = displayedNumber(levelLabel ?? fill);
    const storedLevel = Number(fill.dataset.realtimeLevel);
    const startLevel = Number.isFinite(storedLevel)
        ? storedLevel
        : Number.isFinite(displayedLevel) ? displayedLevel : level;
    const setFill = currentLevel => {
        const height = currentLevel * 165 / 100;
        fill.setAttribute('height', String(height));
        fill.setAttribute('y', String(208 - height));
        fill.dataset.realtimeLevel = String(currentLevel);
    };

    fill.querySelectorAll('animate').forEach(animation => animation.remove());

    if (reduceMotion || startLevel === level) {
        setFill(level);
        return;
    }

    setFill(startLevel);
    const startedAt = performance.now();
    const render = now => {
        const progress = Math.min((now - startedAt) / 650, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        setFill(startLevel + ((level - startLevel) * eased));

        if (progress < 1) {
            levelAnimations.set(fill, window.requestAnimationFrame(render));
        } else {
            levelAnimations.delete(fill);
        }
    };

    levelAnimations.set(fill, window.requestAnimationFrame(render));
}

function updateDeviceRoot(root, snapshot) {
    const device = snapshot.device;
    const telemetry = snapshot.telemetry;
    const health = telemetry.parameter_health ?? {};

    root.dataset.status = device.connection_status;
    root.dataset.lastSeenAt = telemetry.last_seen_at ?? '';
    root.dataset.sendInterval = String(device.send_interval_sec ?? 10);
    root.dataset.usageToday = String(telemetry.usage_today_liter ?? 0);
    root.dataset.usageMonth = String(telemetry.usage_month_liter ?? 0);
    root.dataset.activeAlarmCount = String(device.active_alarm_count ?? 0);

    updateConnectionBadge(root, device.connection_status);
    updateAlarm(root, Number(device.active_alarm_count ?? 0));
    updateQuality(root, telemetry.quality_status);
    updateValve(root, telemetry.valve_state);
    updateLevel(root, telemetry.level_percent);

    setText(root, 'last-update', telemetry.recorded_at_display ?? '—');
    setText(root, 'last-seen', telemetry.last_seen_at_display ?? '—');
    setText(root, 'recorded-at', telemetry.recorded_at_display ?? '—');
    setNumber(root, 'ph', telemetry.ph, 2);
    setNumber(root, 'turbidity', telemetry.turbidity, 2, 'NTU');
    setNumber(root, 'sample-flow', telemetry.sample_flow, 2, 'L/min');
    setNumber(root, 'delta-pulse', telemetry.delta_pulse, 0, 'pulse');
    setNumber(root, 'total-pulse', telemetry.total_pulse, 0, 'pulse');
    setNumber(root, 'usage-today', telemetry.usage_today_liter, 2, 'L');
    setNumber(root, 'usage-month-liter', telemetry.usage_month_liter, 2, 'L');
    setNumber(root, 'usage-month-kl', Number(telemetry.usage_month_liter ?? 0) / 1000, 2, 'KL');
    setNumber(root, 'level-distance-cm', telemetry.level_distance_cm, 2, 'cm');
    setNumber(root, 'level-percent', telemetry.level_percent, 2, '%');
    setNumber(root, 'level-cm', telemetry.level_cm, 2, 'cm');
    setNumber(root, 'voltage-mcu', telemetry.device_voltage_mcu, 2, 'V');
    setNumber(root, 'voltage-5v', telemetry.device_voltage_5v, 2, 'V');
    setNumber(root, 'voltage-input', telemetry.device_voltage_input, 2, 'V');

    updateHealthColor(root, 'ph', health.ph);
    updateHealthColor(root, 'turbidity', health.turbidity);
    updateHealthColor(root, 'sample-flow', health.sample_flow);
    updateHealthColor(root, 'level-percent', health.level_percent);
    updateHealthColor(root, 'level-cm', health.level_percent);
}

function updateDashboardTable(deviceId, snapshot) {
    const row = document.querySelector(`[data-realtime-device-row="${deviceId}"]`);
    if (!row) return;

    const telemetry = snapshot.telemetry;
    setNumber(row, 'ph', telemetry.ph, 2);
    setNumber(row, 'turbidity', telemetry.turbidity, 2, 'NTU');
    setNumber(row, 'level-text', telemetry.level_percent, 0, '%');
    setText(row, 'last-seen', 'just now');
    updateConnectionBadge(row, snapshot.device.connection_status);
    const bar = row.querySelector('[data-realtime-field="level-bar"]');
    if (bar) bar.style.width = `${Math.max(0, Math.min(100, Number(telemetry.level_percent ?? 0)))}%`;
    updateHealthColor(row, 'ph', telemetry.parameter_health?.ph);
    updateHealthColor(row, 'turbidity', telemetry.parameter_health?.turbidity);
}

function refreshDashboardSummary() {
    const cards = Array.from(document.querySelectorAll('.device-card[data-realtime-device-id]'));
    if (cards.length === 0) return;

    const counts = { online: 0, pending: 0, offline: 0 };
    let usageToday = 0;
    let usageMonth = 0;
    cards.forEach(card => {
        counts[card.dataset.status] = (counts[card.dataset.status] ?? 0) + 1;
        usageToday += Number(card.dataset.usageToday ?? 0);
        usageMonth += Number(card.dataset.usageMonth ?? 0);
    });

    document.querySelectorAll('[data-realtime-summary]').forEach(element => {
        const field = element.dataset.realtimeSummary;
        if (field === 'usage-today') {
            animateNumber(element, usageToday / 1000, value => `${formatNumber(value, 2)} KL`);
        } else if (field === 'usage-month') {
            animateNumber(element, usageMonth / 1000, value => `${formatNumber(value, 2)} KL`);
        } else {
            animateNumber(element, counts[field] ?? 0, value => formatNumber(value, 0));
        }
    });
}

function refreshConnectionTimeouts() {
    deviceRoots().forEach(root => {
        if (!root.dataset.lastSeenAt) return;
        const ageSeconds = (Date.now() - new Date(root.dataset.lastSeenAt).getTime()) / 1000;
        const interval = Number(root.dataset.sendInterval ?? 10);
        const status = ageSeconds <= interval * 9 ? 'online' : ageSeconds <= interval * 18 ? 'pending' : 'offline';
        root.dataset.status = status;
        updateConnectionBadge(root, status);
        const row = document.querySelector(`[data-realtime-device-row="${root.dataset.realtimeDeviceId}"]`);
        if (row) updateConnectionBadge(row, status);
    });
    refreshDashboardSummary();
}

function handleTelemetryUpdate(deviceId, event) {
    const snapshot = event.snapshot;
    if (!snapshot) return;

    updateSidebarAlarmCount(deviceId, snapshot.device.active_alarm_count);

    deviceRoots()
        .filter(root => Number(root.dataset.realtimeDeviceId) === Number(deviceId))
        .forEach(root => updateDeviceRoot(root, snapshot));

    updateDashboardTable(deviceId, snapshot);
    refreshDashboardSummary();
    window.appendRealtimeTelemetryPoint?.(snapshot.chart_point);
}

function handleParameterUpdate(deviceId, event) {
    const root = deviceRoots().find(element => Number(element.dataset.realtimeDeviceId) === Number(deviceId));
    if (!root) return;

    root.dataset.lastSeenAt = new Date().toISOString();
    root.dataset.status = 'online';
    updateConnectionBadge(root, 'online');
    if (event.active_alarm_count !== undefined) {
        root.dataset.activeAlarmCount = String(event.active_alarm_count);
        updateAlarm(root, Number(event.active_alarm_count));
        updateSidebarAlarmCount(deviceId, event.active_alarm_count);
    }
    for (const source of event.sources ?? []) {
        const sourceRoot = Array.from(root.querySelectorAll('[data-source-id]'))
            .find(element => Number(element.dataset.sourceId) === Number(source.source_id));
        if (!sourceRoot) continue;
        for (const [key, reading] of Object.entries(source.readings ?? {})) {
            const tile = Array.from(sourceRoot.querySelectorAll('[data-source-parameter]'))
                .find(element => element.dataset.sourceParameter === key);
            if (!tile) continue;
            const value = tile.querySelector('[data-parameter-value]');
            const quality = tile.querySelector('[data-parameter-quality]');
            const time = tile.querySelector('[data-parameter-time]');
            if (value) value.textContent = reading.value == null ? '—' : formatNumber(Number(reading.value), 2);
            if (quality) quality.textContent = reading.quality ?? 'unknown';
            if (time) time.textContent = source.recorded_at ?? '';
        }
    }
    refreshDashboardSummary();
}

function subscribe() {
    if (!window.Echo) return;

    const connection = window.Echo.connector?.pusher?.connection;
    connection?.bind('state_change', ({ current }) => {
        document.querySelectorAll('[data-realtime-connection]').forEach(element => {
            const connected = current === 'connected';
            const connecting = current === 'connecting' || current === 'initialized';
            const dot = element.querySelector('[data-realtime-connection-dot]');
            const label = element.querySelector('[data-realtime-connection-label]');
            dot?.classList.remove('bg-emerald-500', 'bg-amber-400', 'bg-gray-400');
            dot?.classList.add(connected ? 'bg-emerald-500' : connecting ? 'bg-amber-400' : 'bg-gray-400');
            if (label) label.textContent = connected ? 'Realtime' : connecting ? 'Connecting' : 'Realtime offline';
        });
    });

    const deviceIds = [...new Set(deviceRoots().map(root => root.dataset.realtimeDeviceId))];
    deviceIds.forEach(deviceId => {
        window.Echo.private(`devices.${deviceId}`)
            .listen('.telemetry.updated', event => handleTelemetryUpdate(deviceId, event))
            .listen('.parameters.updated', event => handleParameterUpdate(deviceId, event))
            .error(error => console.error(`Realtime channel devices.${deviceId} failed`, error));
    });
}

initializeSidebarAlarmState();
subscribe();
refreshConnectionTimeouts();
window.setInterval(refreshConnectionTimeouts, 10_000);
